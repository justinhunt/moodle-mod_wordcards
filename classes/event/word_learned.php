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

/**
 * The mod_wordcards word learned event.
 *
 * @package    mod_wordcards
 * @copyright  2023 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_wordcards\event;

defined('MOODLE_INTERNAL') || die();

use mod_wordcards\constants;

/**
 * The mod_wordcards word_learned event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - bool submission_editable: is submission editable.
 * }
 *
 * @package    mod_wordcards
 * @since      Moodle 2.6
 * @copyright  2024 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class word_learned extends \core\event\base {

    /**
     * Create instance of event.
     *
     * @since Moodle 2.7
     *
     * @param $wordcards
     * @param $submission
     * @param $editable
     * @return $event
     */
    public static function create_from_term($term, $modulecontext, $association) {
        global $USER;

        $data = [
            'context' => $modulecontext,
            'objectid' => $term->id,
            'userid' => $USER->id,
            'other' => ['termid' => $term->id, 'word' => $term->term, 'successcount' => $association->successcount],
        ];

        /** @var attempt_submitted $event */
        $event = self::create($data);
        $event->add_record_snapshot(constants::M_ASSOCTABLE, $association);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = constants::M_ASSOCTABLE;
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $description = "The user with id '$this->userid' has learned word '".$this->other['word']."'";
        $description .= "(termid: " . $this->other['termid'] . ") with " . $this->other['successcount'];
        $description .= " successful associations in ";
        $description .= "wordcards with course module id '$this->contextinstanceid'.";
        return $description;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventwordcardswordlearned', constants::M_COMPONENT);
    }


    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/wordcards/reports.php',
            ['report' => 'userattempts',
            'attemptid' => $this->objectid,
            'userid' => $this->userid,
            'id' => $this->contextinstanceid]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!array_key_exists('termid', $this->other)) {
            throw new \coding_exception('The \'termid\' value must be set in other.');
        }

        if (!array_key_exists('word', $this->other)) {
            throw new \coding_exception('The \'word\' value must be set in other.');
        }

        if (!array_key_exists('successcount', $this->other)) {
            throw new \coding_exception('The \'successcount\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return ['db' => constants::M_ASSOCTABLE, 'restore' => 'wordcards_associations'];
    }

    public static function get_other_mapping() {
        return false;
    }
}
