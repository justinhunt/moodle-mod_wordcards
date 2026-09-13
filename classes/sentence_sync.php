<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_wordcards;

defined('MOODLE_INTERNAL') || die();

/**
 * Fetch the translated example sentences for terms that do not have them.
 *
 * Terms saved before the Word Wizard began keeping these have nothing stored, and a term looked up
 * while the dictionary had not yet generated the sentences has nothing either. This fills them in
 * afterwards.
 *
 * Nothing here writes output, redirects, or checks a capability. It is meant to be called from
 * wherever suits - a cli script, an adhoc task, or a web service behind a button - and each of
 * those decides for itself who is allowed to ask and what the user sees.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sentence_sync {
    /**
     * Fill in the translated example sentences for one activity's terms.
     *
     * @param int $modid the wordcards instance id
     * @param bool $refresh also re-fetch terms that already have translations stored
     * @param int $limit stop after this many terms are looked up, 0 for no limit
     * @param bool $dryrun work out what would happen and write nothing
     * @return array counts, keyed: terms, nosentence, alreadyhave, updated, unmatched, nodata,
     *               failed, and unmatchedids, the terms no sense could be found for
     */
    public static function sync_module($modid, $refresh = false, $limit = 0, $dryrun = false) {
        global $DB;

        $counts = ['terms' => 0, 'nosentence' => 0, 'alreadyhave' => 0, 'updated' => 0,
            'unmatched' => 0, 'nodata' => 0, 'failed' => 0, 'unmatchedids' => []];

        $mod = \mod_wordcards_module::get_by_modid($modid);
        $sourcelang = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);
        $targetlangs = $mod->get_mod()->deflanguage;

        $terms = $DB->get_records(constants::M_TERMSTABLE, ['modid' => $modid, 'deleted' => 0], 'id ASC');
        $lookedup = 0;

        foreach ($terms as $term) {
            $counts['terms']++;

            // No example sentence means there is nothing to translate.
            if (trim((string)$term->model_sentence) === '') {
                $counts['nosentence']++;
                continue;
            }
            if (!$refresh && trim((string)$term->model_sentence_translations) !== '') {
                $counts['alreadyhave']++;
                continue;
            }
            if ($limit > 0 && $lookedup >= $limit) {
                continue;
            }

            $lookedup++;
            $before = $counts['unmatched'];
            $sentences = self::fetch_sentences_for_term($term, $sourcelang, $targetlangs, $counts);
            if ($sentences === null) {
                if ($counts['unmatched'] > $before) {
                    // No sense carries this sentence. Looking it up again will not change that, so
                    // record it for a caller that can translate it on its own instead.
                    $counts['unmatchedids'][] = $term->id;
                }
                continue;
            }

            if (!$dryrun) {
                $stored = json_encode($sentences, JSON_UNESCAPED_UNICODE);
                $DB->set_field(constants::M_TERMSTABLE, 'model_sentence_translations', $stored, ['id' => $term->id]);
            }
            $counts['updated']++;
        }

        return $counts;
    }

    /**
     * Look one term up in the dictionary and return the translated sentences for its sense.
     *
     * @param \stdClass $term the term record
     * @param string $sourcelang the language being taught, as a dictionary code
     * @param string $targetlangs the definitions language to ask for
     * @param array $counts running totals, updated in place when there is nothing to return
     * @return array|null language code => translated sentence, or null when there is none
     */
    protected static function fetch_sentences_for_term($term, $sourcelang, $targetlangs, &$counts) {

        $payload = utils::fetch_dictionary_entries($term->term, $sourcelang, $targetlangs);
        if (!$payload || !utils::is_json($payload)) {
            $counts['failed']++;
            return null;
        }

        $found = json_decode($payload);
        if (!is_array($found)) {
            $counts['failed']++;
            return null;
        }

        // A term in an activity is one sense, but a lookup returns every sense the dictionary
        // holds. The example sentence is the only thing tying them together: the Word Wizard
        // copied it from the sense when the term was made. A teacher who has since rewritten it
        // gets no match, which is the right answer - the translations on file would be of a
        // sentence that is no longer the one on the card.
        $wanted = self::normalise($term->model_sentence);

        foreach ($found as $onesearch) {
            if (empty($onesearch->results)) {
                continue;
            }
            foreach ($onesearch->results as $sense) {
                if (!isset($sense->example) || self::normalise($sense->example) !== $wanted) {
                    continue;
                }
                $sentences = [];
                foreach (get_object_vars($sense) as $key => $value) {
                    if (strpos($key, 'sent_') === 0 && trim((string)$value) !== '') {
                        $sentences[substr($key, 5)] = $value;
                    }
                }
                if (!$sentences) {
                    // The sense is the right one, the dictionary just has no sentences for it yet.
                    $counts['nodata']++;
                    return null;
                }
                return $sentences;
            }
        }

        $counts['unmatched']++;
        return null;
    }

    /**
     * Translate a term's own example sentence, for one the dictionary does not hold.
     *
     * A teacher can rewrite the example on a card. No dictionary sense then carries that sentence,
     * so sync_module() will never match it however often it runs, and it has to be translated on
     * its own. The translating is done on the Poodll server through the same function the
     * dictionary's own sentences go through, so the sentence uses the word already stored for each
     * language rather than a synonym of it, and goes through the same wrong script check.
     *
     * @param \stdClass $term the term record
     * @return bool true if something was stored
     */
    public static function translate_custom_sentence($term) {
        global $DB;

        if (trim((string)$term->model_sentence) === '') {
            return false;
        }

        $mod = \mod_wordcards_module::get_by_modid($term->modid);
        $sourcelang = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);

        // Only the languages this activity can actually show. The dictionary carries about fifty
        // because a sense is shared by every activity on the platform; a sentence somebody wrote
        // for one card is not, and asking the AI for fifty of them would be hard to justify.
        $targetlangs = self::languages_for_module($mod);
        if (!$targetlangs) {
            return false;
        }

        // Pin each language to the word already on the card.
        $knownwords = '';
        if (!empty($term->translations) && utils::is_json($term->translations)) {
            $words = json_decode($term->translations, true);
            $wanted = array_intersect_key((array)$words, array_flip($targetlangs));
            if ($wanted) {
                $knownwords = json_encode($wanted, JSON_UNESCAPED_UNICODE);
            }
        }

        $langlist = implode(',', $targetlangs);
        $sentences = utils::fetch_sentence_translation(
            $term->term,
            $sourcelang,
            $term->model_sentence,
            $langlist,
            $knownwords
        );
        if (!$sentences) {
            return false;
        }

        $DB->set_field(constants::M_TERMSTABLE, 'model_sentence_translations', $sentences, ['id' => $term->id]);
        return true;
    }

    /**
     * Which languages is it worth translating this activity's sentences into?
     *
     * The language the activity defines in, plus any the learner can switch to, since the card
     * follows whichever they have chosen.
     *
     * @param \mod_wordcards_module $mod
     * @return array of language codes
     */
    protected static function languages_for_module($mod) {
        $langs = [];
        $deflanguage = $mod->get_mod()->deflanguage;
        if (!empty($deflanguage) && $deflanguage !== constants::M_DEFLANG_OTHER) {
            $langs[] = $deflanguage;
        }
        // A learner can only pick another language where the chooser is shown, and then only from
        // the list the chooser itself offers - get_lang_options() is the tts languages, which are
        // a different set of codes entirely.
        if (!empty($mod->get_mod()->showlangchooser)) {
            foreach (utils::get_rcdic_langs($deflanguage, false) as $deflang) {
                if ($deflang['code'] !== constants::M_DEFLANG_OTHER && !in_array($deflang['code'], $langs)) {
                    $langs[] = $deflang['code'];
                }
            }
        }
        // The sentence is already in the language being taught.
        $taught = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);
        return array_values(array_diff($langs, [$taught]));
    }

    /**
     * Reduce a sentence to something two copies of it can be compared on.
     *
     * @param string $text
     * @return string
     */
    protected static function normalise($text) {
        $text = \core_text::strtolower(trim((string)$text));
        return preg_replace('/\s+/u', ' ', $text);
    }
}
