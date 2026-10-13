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

namespace mod_wordcards\local\wordwizard;

use mod_wordcards\utils;

/**
 * Looks words up in the dictionary and adds a chosen sense of each to a word set.
 *
 * The Word Wizard page shows every sense and lets the teacher pick one. Words added in the
 * background, or from the command line, have nobody to pick, so best_sense() picks for them.
 * Both go through the same lookup and the same reading of the dictionary's results here, so
 * a word comes out the same whichever way it was added.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class word_adder {
    /**
     * Look words up in the dictionary.
     *
     * @param string $terms the word, or a comma separated list of words
     * @param string $sourcelang the language of the words, as the dictionary knows it
     * @param string $deflang the language the definitions should be in
     * @param string|null $error set to why the lookup failed, when it does
     * @return array|null one entry per word (see read_entry()), or null if the lookup failed
     */
    public static function lookup($terms, $sourcelang, $deflang, &$error = null) {
        $payload = utils::fetch_dictionary_entries($terms, $sourcelang, $deflang, $error);
        if (!$payload) {
            return null;
        }
        return self::read_payload($payload, $deflang);
    }

    /**
     * Read the dictionary's answer to a lookup.
     *
     * @param string $payload the json the dictionary returned
     * @param string $deflang the language the definitions should be in
     * @return array|null one entry per word (see read_entry()), or null if it could not be read
     */
    public static function read_payload($payload, $deflang) {
        $found = json_decode($payload);
        if (!is_array($found)) {
            return null;
        }
        $entries = [];
        foreach ($found as $onesearch) {
            if (is_object($onesearch) && isset($onesearch->term)) {
                $entries[] = self::read_entry($onesearch, $deflang);
            }
        }
        return $entries;
    }

    /**
     * Read the dictionary's results for one word.
     *
     * Each sense comes back in the shape the Word Wizard template and save_newterm() want:
     * the definition in the definitions language, the example sentence, and the definition
     * and sentence in every language the dictionary sent, as json.
     *
     * @param \stdClass $onesearch one word from the dictionary's answer
     * @param string $deflang the language the definitions should be in
     * @return array with term, found (bool) and senses (list of arrays)
     */
    public static function read_entry($onesearch, $deflang) {
        $senses = [];
        $seen = [];
        if (!empty($onesearch->count) && !empty($onesearch->results)) {
            foreach (array_values((array) $onesearch->results) as $senseindex => $sense) {
                $read = self::read_sense($sense, $senseindex, $deflang);
                // The dictionary sometimes sends a sense twice. Offering both would be offering
                // the same choice twice, so keep the first.
                $key = \core_text::strtolower($read['definition'] . '|' . $read['modelsentence']);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $senses[] = $read;
            }
        }
        return [
            'term' => (string) $onesearch->term,
            'found' => !empty($senses),
            'senses' => $senses,
        ];
    }

    /**
     * Read one sense of a word.
     *
     * @param \stdClass $sense one sense from the dictionary's results
     * @param int $senseindex its position in the results
     * @param string $deflang the language the definition should be in
     * @return array
     */
    protected static function read_sense($sense, $senseindex, $deflang) {
        $translations = [];
        $sentencetranslations = [];
        foreach (get_object_vars($sense) as $key => $value) {
            if (strpos($key, 'lang_') === 0) {
                $translations[substr($key, 5)] = $value;
            } else if (strpos($key, 'sent_') === 0) {
                // The model sentence in that language. The dictionary only sends these once its
                // sentence filling task has reached the sense, so they are often simply absent.
                $sentencetranslations[substr($key, 5)] = $value;
            }
        }

        // The dictionary's own definition is English. In English we prefer its short meaning,
        // in any other language we need the translation.
        if (!empty($sense->meaning)) {
            $englishdefinition = $sense->meaning;
        } else if (!empty($sense->lang_en)) {
            $englishdefinition = $sense->lang_en;
        } else {
            $englishdefinition = $sense->definition ?? '';
        }
        $englishdefinition = trim((string) $englishdefinition);
        if ($deflang === 'en') {
            $definition = $englishdefinition;
        } else {
            $definition = trim((string) ($sense->{'lang_' . $deflang} ?? ''));
        }

        return [
            'senseindex' => $senseindex,
            'definition' => $definition,
            'hasdefinition' => $definition !== '',
            // Kept as the term's source definition. It is the English shown to a learner who
            // picks English, and what the definition becomes if the activity is switched to English.
            'sourcedefinition' => $englishdefinition,
            'modelsentence' => trim((string) ($sense->example ?? '')),
            'translations' => self::to_json($translations),
            'modelsentencetranslations' => self::to_json($sentencetranslations),
        ];
    }

    /**
     * Choose a sense when there is nobody to choose one.
     *
     * The dictionary lists the commonest sense first, so take the first one that has a
     * definition in the definitions language. A sense without one would make a card with no
     * back, which is worse than no card.
     *
     * @param array $entry one word, from read_entry()
     * @return array|null the sense, or null if none will do
     */
    public static function best_sense(array $entry) {
        foreach ($entry['senses'] as $sense) {
            if ($sense['hasdefinition']) {
                return $sense;
            }
        }
        return null;
    }

    /**
     * Add a sense of a word to the word set.
     *
     * @param int $modid the wordcards instance id
     * @param string $term the word
     * @param array $sense the sense, from read_entry()
     * @return int|false the new term id, or false if it was not added (e.g. it is already there)
     */
    public static function add_sense($modid, $term, array $sense) {
        return utils::save_newterm(
            $modid,
            $term,
            $sense['definition'],
            $sense['translations'],
            $sense['sourcedefinition'],
            $sense['modelsentence'],
            $sense['modelsentencetranslations'],
            false
        );
    }

    /**
     * Language code => text, as json. Always a json object, even when empty.
     *
     * @param array $bylang
     * @return string
     */
    protected static function to_json(array $bylang) {
        return json_encode((object) $bylang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
