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
 * External.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

global $CFG;

// This is for pre M4.0 and post M4.0 to work on same code base
require_once($CFG->libdir . '/externallib.php');

/*
 * This is for M4.0 and later
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
*/

use mod_wordcards\utils;
use mod_wordcards\constants;


/**
 * External class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_external extends external_api {

    public static function check_by_phonetic_parameters() {
        return new external_function_parameters(
                 ['spoken' => new external_value(PARAM_TEXT, 'The spoken phrase'),
                       'correct' => new external_value(PARAM_TEXT, 'The correct phrase'),
                       'language' => new external_value(PARAM_TEXT, 'The language eg en-US'),
                 ]
        );

    }
    public static function check_by_phonetic($spoken, $correct, $language) {
        $shortlang = utils::fetch_short_lang($language);
        $spokenphonetic = utils::convert_to_phonetic($spoken, $shortlang);
        $correctphonetic = utils::convert_to_phonetic($correct, $shortlang);
        $similarpercent = 0;
        $similarchars = similar_text($correctphonetic, $spokenphonetic, $similarpercent);
        return round($similarpercent, 0);

    }

    public static function check_by_phonetic_returns() {
        return new external_value(PARAM_INT, 'how close is spoken to correct, 0 - 100');
    }

    public static function mark_as_seen_parameters() {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT),
        ]);
    }

    public static function mark_as_seen($termid) {
        global $DB;

        $params = self::validate_parameters(self::mark_as_seen_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record('wordcards_terms', ['id' => $termid], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());

        // We do not log the completion for teachers.
        if ($mod->can_manage()) {
            return true;
        }

        $mod->require_view();
        return self::mark_as_seen_db($termid);
    }

    private static function mark_as_seen_db(int $termid): bool {
        global $DB, $USER;
        $params = ['userid' => $USER->id, 'termid' => $termid];
        if ($DB->record_exists('wordcards_seen', $params)) {
            return true;
        }

        $record = (object) $params;
        $record->timecreated = time();
        return (bool) $DB->insert_record('wordcards_seen', $record);
    }

    public static function mark_as_seen_returns() {
        return new external_value(PARAM_BOOL);
    }

    public static function report_successful_association_parameters() {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT),
            'isfreemode' => new external_value(PARAM_BOOL, 'True if free mode is being used', VALUE_DEFAULT, 0),
        ]);
    }

    public static function report_successful_association($termid, $isfreemode = false) {
        global $DB;

        $params = self::validate_parameters(self::report_successful_association_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record('wordcards_terms', ['id' => $termid], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());

        self::mark_as_seen_db($term->id);

        // We do not log associations for teachers.
        if ($mod->can_manage()) {
            return true;
        }

        // We need read access.
        $mod->require_view();
        $mod->record_successful_association($term);

        return true;
    }

    public static function report_successful_association_returns() {
        return new external_value(PARAM_BOOL);
    }

    public static function report_failed_association_parameters() {
        return new external_function_parameters([
            'term1id' => new external_value(PARAM_INT),
            'term2id' => new external_value(PARAM_INT),
            'isfreemode' => new external_value(PARAM_BOOL, 'True if free mode is being used', VALUE_DEFAULT, 0),
        ]);
    }

    public static function report_failed_association($term1id, $term2id, $isfreemode = false) {
        global $DB;

        $params = self::validate_parameters(self::report_failed_association_parameters(), compact('term1id', 'term2id'));
        extract($params);

        $term = $DB->get_record('wordcards_terms', ['id' => $term1id], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());
        self::mark_as_seen_db($term->id);

        // We do not log associations for teachers.
        if ($mod->can_manage()) {
            return true;
        }

        // We need read access in at least one of the terms. The rest will be validated elsewhere.
        $mod->require_view();
        $mod->record_failed_association($term, $term2id);

        return true;
    }

    public static function report_failed_association_returns() {
        return new external_value(PARAM_BOOL);
    }

    public static function report_step_grade_parameters() {
        return new external_function_parameters([
                'modid' => new external_value(PARAM_INT),
                'correct' => new external_value(PARAM_INT),
        ]);
    }

    public static function report_step_grade($modid, $correct) {
        $ret = utils::update_stepgrade($modid, $correct);
        return $ret;
    }
    public static function report_step_grade_returns() {
        return new external_value(PARAM_BOOL);
    }

    public static function submit_newterm_parameters() {
        return new external_function_parameters([
            'modid' => new external_value(PARAM_INT),
            'term' => new external_value(PARAM_RAW),
            'definition' => new external_value(PARAM_RAW),
            'translations' => new external_value(PARAM_RAW),
            'sourcedef' => new external_value(PARAM_RAW),
            'modelsentence' => new external_value(PARAM_RAW),
        ]);
    }

    public static function submit_newterm($modid, $term, $definition, $translations, $sourcedef, $modelsentence) {
        $ret = utils::save_newterm($modid, $term, $definition, $translations, $sourcedef, $modelsentence);
        if($ret){
            return true;
        }else{
            return false;
        }
    }
    public static function submit_newterm_returns() {
        return new external_value(PARAM_BOOL);
    }


    public static function submit_mform_parameters() {
        return new external_function_parameters(
                [
                        'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                        'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
                ]
        );
    }

    public static function submit_mform($contextid, $jsonformdata) {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::submit_mform_parameters(),
                ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]);

        $context = context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);

        // Init return object
        $ret = new \stdClass();
        $ret->termid = 0;
        $ret->error = true;
        $ret->message = "";

        list($ignored, $course) = get_context_info_array($context->id);
        $serialiseddata = json_decode($params['jsonformdata']);

        $data = [];
        parse_str($serialiseddata, $data);

        // get filechooser and html editor options
        $audiooptions = utils::fetch_filemanager_opts('audio');
        $imageoptions = utils::fetch_filemanager_opts('image');;

        // get the objects we need
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);

        // we need to pretend this was posted and these help
        $method = 'post';
        $target = '';
        $attributes = null;
        $editable = true;

        //get the mform for imagegen
        $imagegenform = new mod_wordcards_form_imagegen(null,
        ['termid' => $data['termid'] ? $data['termid'] : 0, 'imagemaker' => ''],
        $method, $target, $attributes, $editable, $data);

        if ($imagegendata = $imagegenform->get_data() && !empty($data['draftfileurl'])) {
            $options = (array)$imageoptions;
            if (!isset($options['subdirs'])) {
                $options['subdirs'] = false;
            }
            if (!isset($options['maxfiles'])) {
                $options['maxfiles'] = -1; // unlimited
            }
            if (!isset($options['maxbytes'])) {
                $options['maxbytes'] = 0; // unlimited
            }
            require_once($CFG->dirroot . '/repository/lib.php');
            // Parse the URL to get the draft filearea id and filename
            $parsedurl = parse_url($data['draftfileurl']);
            $path = $parsedurl['path'];

            // Extract the filename from the path
            $filename = basename($path);
            $draftitemid = basename(dirname($path));

            file_save_draft_area_files( $draftitemid, $context->id, constants::M_COMPONENT, 'image', $data['termid'], $options);
            $response = $DB->update_record('wordcards_terms', ['id' => $data['termid'], 'image' => 1]);;
            if(!$response){
                $ret->error = true;
                // $ret->message = $ret->message;
            } else {
                $ret->itemid = $data['termid'];
                $ret->error = false;
            }
        }

        // get the mform for our term
        $mform = new \mod_wordcards_form_term(null,
                ['termid' => $data['termid'] ? $data['termid'] : 0, 'ttslanguage' => $moduleinstance->ttslanguage],
                        $method, $target, $attributes, $editable, $data
                );

        $validateddata = $mform->get_data();
        if ($validateddata) {

            // currently data is an array, but it should be an object
            $data = (object)$data;

            // if this new add and collect data->id
            $needsupdating = false;
            if (empty($data->termid)) {
                $data->modid = $moduleinstance->id;
                $data->id  = $DB->insert_record('wordcards_terms', $data);

                // else set id to termid
            }else{
                $data->id = $data->termid;
                $needsupdating = true;
            }
            if($data->id){
                $ret->error = false;
            }

            // audio data
            if(!empty( $data->audio_filemanager)){
                $data = file_postupdate_standard_filemanager($data, 'audio', $audiooptions, $context, constants::M_COMPONENT, 'audio',
                        $data->id);
                $needsupdating = true;

                // in the case a user has deleted all files, we will still have the draftid in the audio column, we want to set it to 0
                $fs = get_file_storage();
                $areafiles = $fs->get_area_files($context->id, 'mod_wordcards', 'audio', $data->id);
                if(!$areafiles || count($areafiles) == 0){
                    $data->audio = '';
                }else if(count($areafiles) == 1) {
                    $file = array_pop($areafiles);
                    if ($file->is_directory()) {
                        $data->audio = '';
                    }
                }

            }

            // model sentence audio data
            if(!empty($data->model_sentence_audio_filemanager)){
                // $data->audio_filemanager = $audioitemid;
                $data = file_postupdate_standard_filemanager($data, 'model_sentence_audio', $audiooptions, $context, constants::M_COMPONENT, 'model_sentence_audio',
                        $data->id);
                $needsupdating = true;
                // in the case a user has deleted all files, we will still have the draftid in the audio column, we want to set it to 0
                $fs = get_file_storage();
                $areafiles = $fs->get_area_files($context->id, 'mod_wordcards', 'model_sentence_audio', $data->id);

                if(!$areafiles || count($areafiles) == 0){
                    $data->model_sentence_audio = '';
                }else if(count($areafiles) == 1) {
                    $file = array_pop($areafiles);
                    if ($file->is_directory()) {
                        $data->model_sentence_audio = '';
                    }
                }

            }

            if(!empty($data->image_filemanager)){
                $data = file_postupdate_standard_filemanager($data, 'image', $imageoptions, $context, constants::M_COMPONENT, 'image',
                        $data->id);
                $needsupdating = true;

                // in the case a user has deleted all files, we will still have the draftid in the image column, we want to set it to ''
                $fs = get_file_storage();
                $areafiles = $fs->get_area_files($context->id, 'mod_wordcards', 'image', $data->id);
                if(!$areafiles || count($areafiles) == 0){
                    $data->image = '';
                }else if(count($areafiles) == 1) {
                    $file = array_pop($areafiles);
                    if ($file->is_directory()) {
                        $data->image = '';
                    }
                }
            }

            // lets update the passage hash here before we save the item in db
            if ($needsupdating) {
                if($DB->update_record('wordcards_terms', $data)) {
                    // also update our passagehash update flag
                    $DB->update_record('wordcards', ['id' => $moduleinstance->id, 'hashisold' => 1]);
                    $ret->error = false;
                }
            }

            if ($ret->error == true) {
                // $ret->message = $ret->message;
            } else {
                $theitem = $data;
                $ret->itemid = $theitem->id;
                $ret->error = false;
            }
        }
        return json_encode($ret);
    }

    public static function submit_mform_returns() {
        return new external_value(PARAM_RAW);
        // return new external_value(PARAM_INT, 'group id');
    }

    public static function search_dictionary_parameters() {
        return new external_function_parameters(
            ['terms' => new external_value(PARAM_RAW, 'The csv word list'),
                'cmid' => new external_value(PARAM_INT, 'The cmid'),
                'sourcelang' => new external_value(PARAM_TEXT, 'The language searched'),
                'targetlangs' => new external_value(PARAM_TEXT, 'The csv translation langs'),
            ]
        );

    }
    public static function search_dictionary($terms, $cmid, $sourcelang, $targetlangs) {
        $ret = new \stdClass();

        // We need to do this so that search_dictionary requests can run in parallel.
        session_write_close();

        $payload = utils::fetch_dictionary_entries($terms, $sourcelang, $targetlangs);
        if (!$payload) {
            $ret->success = false;
            $ret->payload = "unable to fetch dictionary entries";
        } else {
            $ret->success = true;
            $ret->payload = $payload;
        }
        return $ret;
    }

    public static function search_dictionary_returns() {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_INT, 'Indicates success or failure of the call'),
                'payload' => new external_value(PARAM_RAW, 'If call failed, contains a message about why. Else contains a json string of results'),
            ]
        );
    }


    public static function set_my_words_parameters() {
        return new external_function_parameters(
            [
                'termid' => new external_value(PARAM_INT, 'The term id for the word'),
                'newstatus' => new external_value(PARAM_BOOL, 'The new status (in my words or not)'),
            ]
        );

    }

    /**
     * Set a word as being in "My words" pool or not.
     * @param int $termid
     * @param bool $newstatus
     * @return array
     * @throws invalid_parameter_exception
     */
    public static function set_my_words(int $termid, bool $newstatus) {
        global $DB;
        $params = self::validate_parameters(
            self::set_my_words_parameters(),
            ['termid' => $termid, 'newstatus' => $newstatus]
        );

        $courseandmoduleid = $DB->get_record_sql(
            "SELECT cm.course, cm.id as cmid
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module AND m.name = 'wordcards'
            JOIN {wordcards_terms} wt ON wt.modid = cm.instance AND wt.id = ?",
            [$params['termid']]
        );
        if (!$courseandmoduleid) {
            throw new invalid_parameter_exception('Term not found with id ' . $params['termid']);
        }
        $context = context_module::instance($courseandmoduleid->cmid);
        self::validate_context($context);

        $mywordspool = new \mod_wordcards\my_words_pool($courseandmoduleid->course);
        return [
            'success' => $newstatus ? $mywordspool->add_word($params['termid']) : $mywordspool->remove_word($params['termid']),
            'newStatus' => $newstatus,
        ];
    }

    public static function set_my_words_returns() {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_INT, 'Indicates success or failure of the call'),
                'newStatus' => new external_value(PARAM_BOOL, 'Indicates new status of the word'),
            ]
        );
    }

    public static function report_successful_learnclaim_parameters() {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT),
        ]);
    }

    public static function report_successful_learnclaim($termid) {
        global $DB;

        $params = self::validate_parameters(self::report_successful_association_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record('wordcards_terms', ['id' => $termid], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());

        // We do not log associations for teachers.
        if ($mod->can_manage()) {
            return true;
        }

        // We need read access.
        $mod->require_view();
        $selfclaim = true;
        $mod->record_successful_learn($term, $selfclaim);

        return true;
    }

    public static function report_successful_learnclaim_returns() {
        return new external_value(PARAM_BOOL);
    }

    public static function set_user_preference_parameters() {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'The user preference name'),
            'value' => new external_value(PARAM_TEXT, 'The user preference value'),
        ]);
    }

    public static function set_user_preference($name, $value) {

        //set the user preference
        switch($name){
            case 'wordcards_deflang':
                if (empty($value)) {
                    unset_user_preference($name);
                } else {
                    set_user_preference($name, $value);
                }
                return true;
            default:
                return false;
        }
    }

    public static function set_user_preference_returns() {
        return new external_value(PARAM_BOOL);
    }

}
