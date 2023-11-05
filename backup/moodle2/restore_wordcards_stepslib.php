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
 * Define all the restore steps that will be used by the restore_wordcards_activity_task
 *
 * @package   mod_wordcards
 * @category  backup
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \mod_wordcards\constants;

/**
 * Structure step to restore one wordcards activity
 *
 * @package   mod_wordcards
 * @category  backup
 * @copyright 2016 Your Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_wordcards_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {
        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('wordcards', '/activity/wordcards');
        $paths[] = new restore_path_element('wordcards_term', '/activity/wordcards/terms/term');
        if ($userinfo) {
            $paths[] = new restore_path_element('wordcards_seen', '/activity/wordcards/terms/term/seens/seen');
            $paths[] = new restore_path_element('wordcards_association', '/activity/wordcards/terms/term/associations/association');
            $paths[] = new restore_path_element('wordcards_progress', '/activity/wordcards/progresses/progress');
            $paths[] = new restore_path_element('wordcards_myword', '/activity/wordcards/terms/term/mywords/myword');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data
     *
     * @param array $data parsed element data
     */
    protected function process_wordcards($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        if (empty($data->timecreated)) {
            $data->timecreated = time();
        }
        if (empty($data->timemodified)) {
            $data->timemodified = time();
        }

        // Create the wordcards instance.
        $newitemid = $DB->insert_record('wordcards', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_wordcards_term($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->modid = $this->get_new_parentid('wordcards');

        $newitemid = $DB->insert_record('wordcards_terms', $data);
        $this->set_mapping('wordcards_term', $oldid, $newitemid,true);
    }

    protected function process_wordcards_seen($data) {
        global $DB;

        $data = (object)$data;

        $data->termid = $this->get_new_parentid('wordcards_term');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newitemid = $DB->insert_record(constants::M_SEENTABLE, $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function process_wordcards_association($data) {
        global $DB;

        $data = (object)$data;

        $data->termid = $this->get_new_parentid('wordcards_term');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record(constants::M_ASSOCTABLE, $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function process_wordcards_progress($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->modid = $this->get_new_parentid('wordcards');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record(constants::M_ATTEMPTSTABLE, $data);
        $this->set_mapping('wordcards_progress', $oldid, $newitemid);
    }

    protected function process_wordcards_myword($data) {
        global $DB;

        $data = (object)$data;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->termid = $this->get_new_parentid('wordcards_term');
        $data->courseid = $this->get_courseid();
        $data->timemodified = time();

        if (!$DB->record_exists(constants::M_MYWORDSTABLE, ['userid' => $data->userid, 'termid' => $data->termid, 'courseid' => $data->courseid])) {
            $newitemid = $DB->insert_record(constants::M_MYWORDSTABLE, $data);
            // No need to save this mapping as far as nothing depend on it
            // (child paths, file areas nor links decoder)
        }
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add wordcards related files, no need to match by itemname (just internally handled context).
        $this->add_related_files(constants::M_COMPONENT, 'intro', null);
        $this->add_related_files(constants::M_COMPONENT, 'image', 'wordcards_term');
        $this->add_related_files(constants::M_COMPONENT, 'audio', 'wordcards_term');
        $this->add_related_files(constants::M_COMPONENT, 'model_sentence_audio', 'wordcards_term');
        

    }
}
