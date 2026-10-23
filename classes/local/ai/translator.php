<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_wordcards\local\ai;

use mod_wordcards\constants;
use mod_wordcards\utils;

/**
 * Asks the site's own AI provider for the translations the dictionary did not have.
 *
 * The dictionary is good on common words in common pairs, and patchy elsewhere: an inflected
 * Finnish word can come back with two languages where an activity offers twenty. Whatever this
 * makes stays on this site. Nothing here is ever sent to the dictionary, which only stores what it
 * made itself.
 *
 * The prompts follow the dictionary's own. A word is only ever translated as it is used in the
 * example sentence, because the sentence is what says which sense of the word is meant.
 *
 * Nothing here writes to the database. completer decides what is missing and stores the answers.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translator {
    /** @var int languages asked for in one request, so that a long reply is not cut off */
    const LANGS_PER_REQUEST = 10;

    /**
     * The writing system a language uses, as a PCRE script property, for those not in Latin.
     *
     * An answer must hold at least one character of its own script. Requiring every character to
     * match would be wrong: Japanese mixes three scripts and a sentence may carry Latin names.
     */
    const LANG_SCRIPTS = [
        'ar' => '\p{Arabic}',
        'bg' => '\p{Cyrillic}',
        'bn' => '\p{Bengali}',
        'el' => '\p{Greek}',
        'fa' => '\p{Arabic}',
        'he' => '\p{Hebrew}',
        'hi' => '\p{Devanagari}',
        'ja' => '\p{Hiragana}\p{Katakana}\p{Han}',
        'ko' => '\p{Hangul}\p{Han}',
        'ru' => '\p{Cyrillic}',
        'rus' => '\p{Cyrillic}',
        'ta' => '\p{Tamil}',
        'te' => '\p{Telugu}',
        'th' => '\p{Thai}',
        'ti' => '\p{Ethiopic}',
        'uk' => '\p{Cyrillic}',
        'ur' => '\p{Arabic}',
        'zh' => '\p{Han}',
        'zh_tw' => '\p{Han}',
    ];

    /** @var \context where the AI is used, which is where the site counts the use */
    protected $context;

    /** @var int the user the AI is used on behalf of */
    protected $userid;

    /**
     * Constructor.
     *
     * @param \context $context
     * @param int $userid
     */
    public function __construct(\context $context, $userid) {
        $this->context = $context;
        $this->userid = (int) $userid;
    }

    /**
     * Can this user have the site's AI fill in translations here?
     *
     * Every one of these has to hold: the site switch, a Moodle with an AI subsystem (4.5 and
     * later), a provider that can generate text, AI allowed in this course and activity, the
     * capability, and the user having accepted the site's AI policy.
     *
     * @param \context $context the activity's context
     * @param int|null $userid defaults to the current user
     * @param bool $checkpolicy false to leave out the AI policy, for a page that can ask for it
     * @return bool
     */
    public static function is_available(\context $context, $userid = null, $checkpolicy = true) {
        global $USER;

        if (empty(get_config(constants::M_COMPONENT, 'localaitranslations'))) {
            return false;
        }
        if (!class_exists('\core_ai\manager') || !class_exists('\core\di')) {
            return false;
        }
        $userid = $userid === null ? $USER->id : (int) $userid;
        if (empty($userid) || isguestuser($userid)) {
            return false;
        }
        if (!has_capability('mod/wordcards:useai', $context, $userid)) {
            return false;
        }

        $actionclass = \core_ai\aiactions\generate_text::class;
        $manager = \core\di::get(\core_ai\manager::class);
        if (!$manager->is_action_available($actionclass)) {
            return false;
        }
        // The course and activity switches for AI arrived in Moodle 5.0.
        if (
            method_exists($manager, 'is_action_enabled_in_context')
                && !$manager->is_action_enabled_in_context($context, $actionclass)
        ) {
            return false;
        }
        return !$checkpolicy || self::policy_accepted($userid);
    }

    /**
     * Has the user accepted the site's AI policy?
     *
     * @param int $userid
     * @return bool
     */
    public static function policy_accepted($userid) {
        return class_exists('\core_ai\manager') && \core_ai\manager::get_user_policy_status($userid);
    }

    /**
     * Write a short example sentence for a word that has none.
     *
     * Without a sentence there is nothing to say which sense of the word is meant, except the
     * definition and the word in other languages, so the prompt leans on those.
     *
     * @param string $term the word
     * @param string $sourcelang the language of the word, as a dictionary code
     * @param string $definition the definition, in the activity's definitions language
     * @param string $deflang the definitions language
     * @param array $knownwords language code => the word in that language
     * @return string the sentence, or an empty string if none came back
     */
    public function generate_sentence($term, $sourcelang, $definition, $deflang, array $knownwords = []) {
        $sourcename = self::lang_name($sourcelang);
        $theprompt = "Please give me a simple short sentence in $sourcename to illustrate the use of the "
            . "word/phrase \"$term\".";
        if (trim((string) $definition) !== '') {
            $theprompt .= " It is meant in the sense of \"$definition\" (" . self::lang_name($deflang) . ").";
        }
        $pairs = self::describe_words($knownwords);
        if ($pairs) {
            $theprompt .= " In other languages it is: $pairs.";
        }
        $theprompt .= " Use the word itself, inflected as the grammar requires, and keep the sentence natural.";
        $theprompt .= " When it is not possible to return a good sentence, return an empty string.";
        $theprompt .= " Please reply with a json object of the following format {\"sentence\": \"the sample sentence\"}"
            . " and no additional comment.";

        $reply = $this->ask($theprompt);
        if (!is_array($reply) || !isset($reply['sentence']) || !is_string($reply['sentence'])) {
            return '';
        }
        $sentence = trim($reply['sentence']);
        // A sentence in the wrong script is the model having answered in another language.
        if (self::is_wrong_script($sourcelang, $sentence)) {
            return '';
        }
        return $sentence;
    }

    /**
     * Suggest meanings for a word or phrase the dictionary does not have at all.
     *
     * This is how an inflected form ("Helsingissä"), an idiom or any phrase a teacher types gets a
     * card. It is made for this site only: the dictionary holds what it found itself, not phrases
     * one teacher wanted. Something mistyped comes back as nothing, or as nonsense the teacher can
     * see and skip, which is all that can be asked of it.
     *
     * @param string $term the word or phrase, exactly as typed
     * @param string $sourcelang the language of it, as a dictionary code
     * @param string $deflang the language the definitions should be in
     * @param int $max the most meanings to suggest
     * @return array of meanings, the commonest first, each with definition (in $deflang),
     *               sourcedefinition (in English) and modelsentence
     */
    public function create_senses($term, $sourcelang, $deflang, $max = 3) {
        $sourcename = self::lang_name($sourcelang);
        $defname = self::lang_name($deflang);
        $theprompt = "\"$term\" is a word, an inflected form of a word, or a phrase in $sourcename. Give up to $max"
            . " distinct meanings it is commonly used with, the commonest first. Give fewer if it has fewer;"
            . " one is fine.";
        $theprompt .= " For each meaning give: \"d\", the meaning as it would go on the back of a flashcard for a"
            . " learner who reads $defname, which is a short equivalent word or phrase in $defname, not an"
            . " explanation; \"en\", the same in English; and \"s\", a short simple example sentence in"
            . " $sourcename that uses \"$term\" exactly as written, in that meaning.";
        $theprompt .= " An inflected form keeps its inflection in \"d\" and \"en\": a word meaning \"in Helsinki\""
            . " is \"in Helsinki\", not \"Helsinki\".";
        $theprompt .= " Write \"d\" and \"en\" as they would be written on their own in a word list: no capital"
            . " letter unless the language always uses one there, such as for names or German nouns.";
        $theprompt .= " If \"$term\" is not $sourcename at all, reply with an empty list.";
        $theprompt .= " Please reply with a json object of the following format"
            . " {\"senses\": [{\"d\": \"meaning\", \"en\": \"meaning in English\", \"s\": \"example sentence\"}]}"
            . " and no additional comment.";

        $reply = $this->ask($theprompt);
        if (!is_array($reply) || !isset($reply['senses']) || !is_array($reply['senses'])) {
            return [];
        }

        $senses = [];
        foreach ($reply['senses'] as $sense) {
            if (!is_array($sense)) {
                continue;
            }
            $definition = isset($sense['d']) && is_string($sense['d']) ? trim($sense['d']) : '';
            $english = isset($sense['en']) && is_string($sense['en']) ? trim($sense['en']) : '';
            $sentence = isset($sense['s']) && is_string($sense['s']) ? trim($sense['s']) : '';
            if ($deflang === 'en' && $definition === '') {
                $definition = $english;
            }
            // A card needs its back, and a sentence to say which meaning it is.
            if (
                $definition === '' || $sentence === ''
                    || self::is_wrong_script($deflang, $definition) || self::is_wrong_script($sourcelang, $sentence)
            ) {
                continue;
            }
            if (self::is_wrong_script('en', $english)) {
                $english = '';
            }
            $senses[] = ['definition' => $definition, 'sourcedefinition' => $english, 'modelsentence' => $sentence];
            if (count($senses) >= $max) {
                break;
            }
        }
        return $senses;
    }

    /**
     * Translate a word as it is used in its example sentence, with the sentence too.
     *
     * Languages in $knownwords already have their word, and that is the word that stays. It is
     * passed along so the translated sentence is built around it rather than around a synonym.
     *
     * @param string $term the word
     * @param string $sourcelang the language of the word and sentence, as a dictionary code
     * @param string $sentence the example sentence
     * @param array $langs the language codes to ask about
     * @param array $knownwords language code => the word already decided for it
     * @return array language code => ['w' => word, 's' => sentence], either possibly empty
     */
    public function translate($term, $sourcelang, $sentence, array $langs, array $knownwords = []) {
        $results = [];
        foreach (array_chunk(array_values($langs), self::LANGS_PER_REQUEST) as $chunk) {
            $known = array_intersect_key($knownwords, array_flip($chunk));
            $results += $this->translate_chunk($term, $sourcelang, $sentence, $chunk, $known);
        }
        return $results;
    }

    /**
     * One request's worth of translate().
     *
     * @param string $term
     * @param string $sourcelang
     * @param string $sentence
     * @param array $langs
     * @param array $knownwords
     * @return array
     */
    protected function translate_chunk($term, $sourcelang, $sentence, array $langs, array $knownwords) {
        $described = [];
        foreach ($langs as $code) {
            $described[] = $code . ' (' . self::lang_name($code) . ')';
        }
        $describedlangs = implode(', ', $described);
        $sourcename = self::lang_name($sourcelang);

        $theprompt = "For the following languages \"$describedlangs\", please give me the equivalent word/phrase for"
            . " \"$term\" ($sourcename) as it is used in this sentence \"$sentence\".";
        $theprompt .= " Also translate the whole sentence into each of those languages.";
        if ($knownwords) {
            $theprompt .= " For these languages the word is already decided: " . self::describe_words($knownwords) . ".";
            $theprompt .= " Return each of those words unchanged as its \"w\", and make sure the translated sentence"
                . " for that language actually uses that exact word, inflected as the grammar requires.";
        }
        // Without this a word that opens the sentence comes back capitalised, as German "Wie" for
        // Finnish "millainen", and that is how it would show on the card.
        $theprompt .= " Give each \"w\" as it would be written on its own in a word list: do not capitalise it just"
            . " because it begins the sentence, but keep the capitals the language always uses, such as for"
            . " names or German nouns. This is for \"w\" only: each \"s\" is an ordinary sentence and starts"
            . " with a capital letter wherever the language uses them.";
        $theprompt .= " Use each language's own script, and do not transliterate. If you cannot translate reliably"
            . " into one of them, leave that language out rather than guessing or answering in English.";
        $theprompt .= " Please reply with a json object keyed by the language code exactly as given, whose values are"
            . " objects holding the word as \"w\" and the translated sentence as \"s\", of the following format"
            . " {\"lang\": {\"w\": \"word\", \"s\": \"sentence\"}} and no additional comment.";

        $reply = $this->ask($theprompt);
        if (!is_array($reply)) {
            return [];
        }

        $results = [];
        foreach ($reply as $code => $value) {
            // The model answers to ru, where wordcards keeps Russian as rus.
            if ($code === 'ru' && in_array('rus', $langs)) {
                $code = 'rus';
            }
            if (!in_array($code, $langs, true) || !is_array($value)) {
                continue;
            }
            $word = isset($value['w']) && is_string($value['w']) ? trim($value['w']) : '';
            $thesentence = isset($value['s']) && is_string($value['s']) ? trim($value['s']) : '';
            // Leaving out an answer in the wrong script means a later run can try again, where
            // storing it means a learner is shown something they cannot read.
            if (self::is_wrong_script($code, $word)) {
                $word = '';
            }
            if (self::is_wrong_script($code, $thesentence)) {
                $thesentence = '';
            }
            if ($word !== '' || $thesentence !== '') {
                $results[$code] = ['w' => $word, 's' => $thesentence];
            }
        }
        return $results;
    }

    /**
     * Send a prompt to the site's AI and read the json object in the reply.
     *
     * The generate text action has no json mode, so a reply may arrive wrapped in a code fence or
     * with a sentence before it. Take the outermost braces and try those.
     *
     * @param string $prompt
     * @return array|null the decoded object, or null if there was none
     */
    protected function ask($prompt) {
        $action = new \core_ai\aiactions\generate_text(
            contextid: $this->context->id,
            userid: $this->userid,
            prompttext: $prompt,
        );
        $manager = \core\di::get(\core_ai\manager::class);
        $response = $manager->process_action($action);
        if (!$response->get_success()) {
            debugging('mod_wordcards AI request failed: ' . $response->get_errormessage(), DEBUG_DEVELOPER);
            return null;
        }
        $data = $response->get_response_data();
        return self::read_json($data['generatedcontent'] ?? '');
    }

    /**
     * Find the json object in some text.
     *
     * @param string $text
     * @return array|null
     */
    public static function read_json($text) {
        $text = trim((string) $text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Is this text written in the wrong script for its language?
     *
     * The same test the dictionary applies to its own answers. A value with no letters is left
     * alone, since a numeral is a fine translation of a number word; one with no digits either,
     * such as a bare dash, is not.
     *
     * @param string $lang the language code
     * @param string $text
     * @return bool true if it should not be stored
     */
    public static function is_wrong_script($lang, $text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }
        $hasletter = preg_match('/\p{L}/u', $text);
        if ($hasletter === false) {
            return false;
        }
        if (!$hasletter) {
            return !preg_match('/\p{N}/u', $text);
        }
        if (isset(self::LANG_SCRIPTS[$lang])) {
            $inscript = preg_match('/[' . self::LANG_SCRIPTS[$lang] . ']/u', $text);
            return $inscript === false ? false : !$inscript;
        }
        // A language written in Latin script. Any letter that is not Latin is another language's
        // word, or a homoglyph that no reader would spot.
        $foreign = preg_match('/(?!\p{Latin})\p{L}/u', $text);
        return $foreign === false ? false : (bool) $foreign;
    }

    /**
     * The English name of a language, for a prompt.
     *
     * A bare code is ambiguous to a model: asked for "so" and "ti" it has been seen to answer in
     * English and Esperanto rather than Somali and Tigrinya.
     *
     * @param string $code a dictionary language code
     * @return string
     */
    public static function lang_name($code) {
        static $names = null;
        if ($names === null) {
            $names = [];
            foreach (utils::get_rcdic_langs('en', false) as $langdef) {
                $names[$langdef['code']] = $langdef['name'];
            }
        }
        return $names[$code] ?? $code;
    }

    /**
     * Language code => word, as a list for a prompt.
     *
     * @param array $words
     * @return string
     */
    protected static function describe_words(array $words) {
        $pairs = [];
        foreach ($words as $code => $word) {
            if (is_string($word) && trim($word) !== '') {
                $pairs[] = $code . ' (' . self::lang_name($code) . ') = "' . trim($word) . '"';
            }
        }
        return implode('; ', $pairs);
    }
}
