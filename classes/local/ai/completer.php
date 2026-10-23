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
use mod_wordcards\sentence_sync;
use mod_wordcards\task\complete_translations;
use mod_wordcards\utils;

/**
 * Fills in, with the site's AI, the translations a term's dictionary entry did not have.
 *
 * Only what is missing is asked for, and nothing already stored is replaced: a word the dictionary
 * gave stays, and the sentence for a language is built around it. A term with no example sentence
 * is given one first, since without a sentence there is nothing to say which sense of the word a
 * translation should be in.
 *
 * Which translated sentences are asked for depends on where the example sentence came from. One
 * the dictionary gave will have its sentences filled in by the dictionary in time, for every
 * language it has a word in (see sentence_sync), so here only the languages the dictionary had no
 * word for are given sentences. A sentence of the teacher's own, or one made here, is held by no
 * dictionary entry, so every language is translated here.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completer {
    /**
     * Work out what a term is missing.
     *
     * @param \stdClass $term the term record
     * @param \mod_wordcards_module $mod
     * @param bool $customsentence the example sentence is the teacher's own, not the dictionary's
     * @return array needsentence (bool), wordlangs and sentencelangs (language codes still to be
     *               filled in) and knownwords (language code => the word already decided)
     */
    public static function plan($term, \mod_wordcards_module $mod, $customsentence) {
        $langs = sentence_sync::languages_for_module($mod);
        $knownwords = self::known_words($term, $mod);
        $sentences = self::decode($term->model_sentence_translations);
        $needsentence = trim((string) $term->model_sentence) === '';

        $wordlangs = array_values(array_diff($langs, array_keys($knownwords)));
        if ($needsentence || $customsentence) {
            // No dictionary entry holds this sentence, so every language is ours to translate.
            $sentencelangs = $langs;
        } else {
            $sentencelangs = $wordlangs;
        }
        $sentencelangs = array_values(array_filter($sentencelangs, function ($code) use ($sentences) {
            return !isset($sentences[$code]) || trim((string) $sentences[$code]) === '';
        }));

        return [
            'needsentence' => $needsentence,
            'wordlangs' => $wordlangs,
            'sentencelangs' => $sentencelangs,
            'knownwords' => $knownwords,
        ];
    }

    /**
     * Is there anything to fill in for this term?
     *
     * @param array $plan from plan()
     * @return bool
     */
    public static function has_work(array $plan) {
        return $plan['needsentence'] || $plan['wordlangs'] || $plan['sentencelangs'];
    }

    /**
     * Fill in what a term is missing, and store it.
     *
     * @param \stdClass $term the term record
     * @param bool $customsentence the example sentence is the teacher's own, not the dictionary's
     * @param translator $translator
     * @return array sentencemade (bool), words and sentences (how many were stored), and
     *               message, saying why nothing was done when nothing was
     */
    public static function complete_term($term, $customsentence, translator $translator) {
        global $DB;

        $result = ['sentencemade' => false, 'words' => 0, 'sentences' => 0, 'message' => ''];
        $mod = \mod_wordcards_module::get_by_modid($term->modid);
        $sourcelang = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);

        $plan = self::plan($term, $mod, $customsentence);
        if (!self::has_work($plan)) {
            $result['message'] = 'nothing missing';
            return $result;
        }

        if ($plan['needsentence']) {
            $sentence = $translator->generate_sentence(
                $term->term,
                $sourcelang,
                $term->definition,
                $mod->get_mod()->deflanguage,
                $plan['knownwords']
            );
            if ($sentence === '') {
                $result['message'] = 'no example sentence could be made, so there is nothing to translate from';
                return $result;
            }
            $term->model_sentence = $sentence;
            $DB->set_field(constants::M_TERMSTABLE, 'model_sentence', $sentence, ['id' => $term->id]);
            // The sentence needs its audio made.
            $DB->set_field(constants::M_TABLE, 'hashisold', 1, ['id' => $term->modid]);
            $result['sentencemade'] = true;
            // The sentence is new, so the plan for its translations is too.
            $plan = self::plan($term, $mod, true);
        }

        $asklangs = array_values(array_unique(array_merge($plan['wordlangs'], $plan['sentencelangs'])));
        if (!$asklangs) {
            return $result;
        }
        $answers = $translator->translate(
            $term->term,
            $sourcelang,
            $term->model_sentence,
            $asklangs,
            array_intersect_key($plan['knownwords'], array_flip($asklangs))
        );

        // Read the term again: a teacher may have edited it while the AI was answering, and what
        // they wrote wins.
        $current = $DB->get_record(constants::M_TERMSTABLE, ['id' => $term->id]);
        if (!$current || $current->model_sentence !== $term->model_sentence) {
            $result['message'] = 'the term changed while it was being translated';
            return $result;
        }
        $words = self::decode($current->translations);
        $sentences = self::decode($current->model_sentence_translations);
        foreach ($answers as $code => $answer) {
            if (
                in_array($code, $plan['wordlangs']) && $answer['w'] !== ''
                    && (!isset($words[$code]) || trim((string) $words[$code]) === '')
            ) {
                $words[$code] = $answer['w'];
                $result['words']++;
            }
            if (
                in_array($code, $plan['sentencelangs']) && $answer['s'] !== ''
                    && (!isset($sentences[$code]) || trim((string) $sentences[$code]) === '')
            ) {
                $sentences[$code] = $answer['s'];
                $result['sentences']++;
            }
        }

        $update = ['id' => $term->id];
        if ($result['words']) {
            $update['translations'] = self::encode($words);
        }
        if ($result['sentences']) {
            $update['model_sentence_translations'] = self::encode($sentences);
        }
        if (count($update) > 1) {
            $DB->update_record(constants::M_TERMSTABLE, (object) $update);
        }
        if (!$result['words'] && !$result['sentences']) {
            $result['message'] = 'the AI returned nothing usable';
        }
        return $result;
    }

    /**
     * Queue a term to be completed, if the AI may be used and there is something to do.
     *
     * @param \stdClass $term the term record
     * @param \mod_wordcards_module $mod
     * @param bool $customsentence the example sentence is the teacher's own, not the dictionary's
     * @return bool whether it was queued
     */
    public static function queue_if_needed($term, \mod_wordcards_module $mod, $customsentence) {
        global $USER;
        if (!translator::is_available($mod->get_context(), $USER->id)) {
            return false;
        }
        if (!self::has_work(self::plan($term, $mod, $customsentence))) {
            return false;
        }
        complete_translations::queue_for_term($term->id, $customsentence, $USER->id);
        return true;
    }

    /**
     * Queue every term in an activity that is missing something.
     *
     * The sentences on terms already in the set are taken to be the dictionary's: there is no
     * telling now which ones a teacher wrote.
     *
     * @param \mod_wordcards_module $mod
     * @return int how many terms were queued
     */
    public static function queue_module(\mod_wordcards_module $mod) {
        global $DB, $USER;
        if (!translator::is_available($mod->get_context(), $USER->id)) {
            return 0;
        }
        $queued = 0;
        $terms = $DB->get_records(constants::M_TERMSTABLE, ['modid' => $mod->get_id(), 'deleted' => 0], 'id ASC');
        foreach ($terms as $term) {
            if (self::has_work(self::plan($term, $mod, false))) {
                complete_translations::queue_for_term($term->id, false, $USER->id);
                $queued++;
            }
        }
        return $queued;
    }

    /**
     * The word already decided for each language.
     *
     * The definition is the word in the definitions language, and the source definition the word
     * in English, whether or not the translations json repeats them.
     *
     * @param \stdClass $term
     * @param \mod_wordcards_module $mod
     * @return array language code => word
     */
    protected static function known_words($term, \mod_wordcards_module $mod) {
        $known = array_filter(self::decode($term->translations), function ($word) {
            return is_string($word) && trim($word) !== '';
        });
        $deflanguage = $mod->get_mod()->deflanguage;
        if (
            !isset($known[$deflanguage]) && trim((string) $term->definition) !== ''
                && $deflanguage !== constants::M_DEFLANG_OTHER
        ) {
            $known[$deflanguage] = trim($term->definition);
        }
        if (!isset($known['en']) && trim((string) ($term->sourcedef ?? '')) !== '') {
            $known['en'] = trim($term->sourcedef);
        }
        return $known;
    }

    /**
     * Language code => text, from the json stored on a term.
     *
     * @param string|null $json
     * @return array
     */
    protected static function decode($json) {
        if (trim((string) $json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Language code => text, as json. Always a json object, even when empty.
     *
     * @param array $bylang
     * @return string
     */
    protected static function encode(array $bylang) {
        return json_encode((object) $bylang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
