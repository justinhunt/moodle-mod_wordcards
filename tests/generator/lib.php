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

/**
 * Data generator for mod_wordcards.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_wordcards_generator extends testing_module_generator {
    /**
     * Create a wordcards instance.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;
        $defaults = [
            'ttslanguage' => 'en-US',
            'deflanguage' => 'en',
            'showlangchooser' => 0,
            // The form always sends these, so the table and the grade item code expect them.
            'viewstart' => 0,
            'grade' => 0,
        ];
        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }
        return parent::create_instance($record, (array) $options);
    }

    /**
     * Add a term to a wordcards instance.
     *
     * @param int $modid
     * @param array $fields
     * @return stdClass the term record
     */
    public function create_term($modid, array $fields) {
        global $DB;
        $term = (object) array_merge(['modid' => $modid, 'term' => 'word', 'definition' => ''], $fields);
        $term->id = $DB->insert_record('wordcards_terms', $term);
        return $DB->get_record('wordcards_terms', ['id' => $term->id]);
    }
}
