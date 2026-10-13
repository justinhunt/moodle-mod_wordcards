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
 * @package    mod_wordcards
 * @author     Frédéric Massart - FMCorz.net
 * @copyright  2016 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
use mod_wordcards\imagegen;
use mod_wordcards\sentence_sync;
use mod_wordcards\local\wordwizard\background;
use mod_wordcards\local\wordwizard\word_adder;


/**
 * External class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_external extends external_api
{

    public static function check_by_phonetic_parameters()
    {
        return new external_function_parameters(
            [
                'spoken' => new external_value(PARAM_TEXT, 'The spoken phrase'),
                'correct' => new external_value(PARAM_TEXT, 'The correct phrase'),
                'language' => new external_value(PARAM_TEXT, 'The language eg en-US'),
            ]
        );

    }
    public static function check_by_phonetic($spoken, $correct, $language)
    {
        $shortlang = utils::fetch_short_lang($language);
        $spokenphonetic = utils::convert_to_phonetic($spoken, $shortlang);
        $correctphonetic = utils::convert_to_phonetic($correct, $shortlang);
        $similarpercent = 0;
        $similarchars = similar_text($correctphonetic, $spokenphonetic, $similarpercent);
        return round($similarpercent, 0);

    }

    public static function check_by_phonetic_returns()
    {
        return new external_value(PARAM_INT, 'how close is spoken to correct, 0 - 100');
    }

    public static function mark_as_seen_parameters()
    {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT),
        ]);
    }

    public static function mark_as_seen($termid)
    {
        global $DB;

        $params = self::validate_parameters(self::mark_as_seen_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record(constants::M_TERMSTABLE, ['id' => $termid], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());

        // We do not log the completion for teachers.
        if ($mod->can_manage()) {
            return true;
        }

        $mod->require_view();
        return self::mark_as_seen_db($termid);
    }

    private static function mark_as_seen_db(int $termid): bool
    {
        global $DB, $USER;
        $params = ['userid' => $USER->id, 'termid' => $termid];
        if ($DB->record_exists('wordcards_seen', $params)) {
            return true;
        }

        $record = (object) $params;
        $record->timecreated = time();
        return (bool) $DB->insert_record('wordcards_seen', $record);
    }

    public static function mark_as_seen_returns()
    {
        return new external_value(PARAM_BOOL);
    }

    public static function report_successful_association_parameters()
    {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT),
            'isfreemode' => new external_value(PARAM_BOOL, 'True if free mode is being used', VALUE_DEFAULT, 0),
        ]);
    }

    public static function report_successful_association($termid, $isfreemode = false)
    {
        global $DB;

        $params = self::validate_parameters(self::report_successful_association_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record(constants::M_TERMSTABLE, ['id' => $termid], '*', MUST_EXIST);
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

    public static function report_successful_association_returns()
    {
        return new external_value(PARAM_BOOL);
    }

    public static function report_failed_association_parameters()
    {
        return new external_function_parameters([
            'term1id' => new external_value(PARAM_INT),
            'term2id' => new external_value(PARAM_INT),
            'isfreemode' => new external_value(PARAM_BOOL, 'True if free mode is being used', VALUE_DEFAULT, 0),
        ]);
    }

    public static function report_failed_association($term1id, $term2id, $isfreemode = false)
    {
        global $DB;

        $params = self::validate_parameters(self::report_failed_association_parameters(), compact('term1id', 'term2id'));
        extract($params);

        $term = $DB->get_record(constants::M_TERMSTABLE, ['id' => $term1id], '*', MUST_EXIST);
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

    public static function report_failed_association_returns()
    {
        return new external_value(PARAM_BOOL);
    }

    public static function report_step_grade_parameters()
    {
        return new external_function_parameters([
            'modid' => new external_value(PARAM_INT),
            'correct' => new external_value(PARAM_INT),
        ]);
    }

    public static function report_step_grade($modid, $correct)
    {
        $ret = utils::update_stepgrade($modid, $correct);
        return $ret;
    }
    public static function report_step_grade_returns()
    {
        return new external_value(PARAM_BOOL);
    }

    public static function submit_newterm_parameters()
    {
        return new external_function_parameters([
            'modid' => new external_value(PARAM_INT),
            'term' => new external_value(PARAM_RAW),
            'definition' => new external_value(PARAM_RAW),
            'translations' => new external_value(PARAM_RAW),
            'sourcedef' => new external_value(PARAM_RAW),
            'modelsentence' => new external_value(PARAM_RAW),
            'modelsentencetranslations' => new external_value(
                PARAM_RAW,
                'The model sentence in each definitions language, as json',
                VALUE_DEFAULT,
                ''
            ),
            'sentenceedited' => new external_value(
                PARAM_BOOL,
                'Whether the teacher rewrote the sentence the dictionary offered',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    public static function submit_newterm(
        $modid,
        $term,
        $definition,
        $translations,
        $sourcedef,
        $modelsentence,
        $modelsentencetranslations = '',
        $sentenceedited = false
    ) {
        $params = self::validate_parameters(
            self::submit_newterm_parameters(),
            compact(
                'modid',
                'term',
                'definition',
                'translations',
                'sourcedef',
                'modelsentence',
                'modelsentencetranslations',
                'sentenceedited'
            )
        );

        $mod = mod_wordcards_module::get_by_modid($params['modid']);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        $ret = utils::save_newterm(
            $params['modid'],
            $params['term'],
            $params['definition'],
            $params['translations'],
            $params['sourcedef'],
            $params['modelsentence'],
            $params['modelsentencetranslations'],
            $params['sentenceedited']
        );
        // A word is only added once, so when nothing was added say whether that is why.
        return [
            'added' => !empty($ret),
            'termid' => $ret ? (int) $ret : 0,
            'alreadyinset' => !$ret && utils::term_exists($params['modid'], $params['term']),
        ];
    }
    public static function submit_newterm_returns()
    {
        return new external_single_structure([
            'added' => new external_value(PARAM_BOOL, 'Whether the word was added'),
            'termid' => new external_value(PARAM_INT, 'The new term id, or 0 if it was not added'),
            'alreadyinset' => new external_value(PARAM_BOOL, 'Whether it was not added because the word is already there'),
        ]);
    }


    public static function submit_mform_parameters()
    {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array'),
            ]
        );
    }

    public static function submit_mform($contextid, $jsonformdata)
    {
        global $CFG, $DB, $USER;

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(
            self::submit_mform_parameters(),
            ['contextid' => $contextid, 'jsonformdata' => $jsonformdata]
        );

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
        $imageoptions = utils::fetch_filemanager_opts('image');
        ;

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
        $imagegenform = new mod_wordcards_form_imagegen(
            null,
            ['termid' => $data['termid'] ? $data['termid'] : 0, 'imagemaker' => ''],
            $method,
            $target,
            $attributes,
            $editable,
            $data
        );

        if ($imagegendata = $imagegenform->get_data() && !empty($data['draftfileurl'])) {
            $options = (array) $imageoptions;
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

            file_save_draft_area_files($draftitemid, $context->id, constants::M_COMPONENT, 'image', $data['termid'], $options);
            $response = $DB->update_record(constants::M_TERMSTABLE, ['id' => $data['termid'], 'image' => 1, 'imageversion' => time()]);
            if (!$response) {
                $ret->error = true;
                // $ret->message = $ret->message;
            } else {
                $ret->itemid = $data['termid'];
                $ret->error = false;
            }
        }

        // get the mform for our term
        $mform = new \mod_wordcards_form_term(
            null,
            ['termid' => $data['termid'] ? $data['termid'] : 0, 'ttslanguage' => $moduleinstance->ttslanguage],
            $method,
            $target,
            $attributes,
            $editable,
            $data
        );

        $validateddata = $mform->get_data();
        if ($validateddata) {

            // currently data is an array, but it should be an object
            $data = (object) $data;

            // if this new add and collect data->id
            $needsupdating = false;
            if (empty($data->termid)) {
                $data->modid = $moduleinstance->id;
                $data->id = $DB->insert_record(constants::M_TERMSTABLE, $data);

                // else set id to termid
            } else {
                $data->id = $data->termid;
                $needsupdating = true;

                // The stored sentence translations are of the sentence as it was. Rewriting it
                // leaves them describing something that is no longer on the card, so they go, and
                // a task is asked to translate the new one.
                $fields = 'id, modid, model_sentence, model_sentence_translations';
                $existing = $DB->get_record(constants::M_TERMSTABLE, ['id' => $data->id], $fields);
                if ($existing && trim((string)$existing->model_sentence) !== trim((string)$data->model_sentence)) {
                    $data->model_sentence_translations = '';
                    if (trim((string)$data->model_sentence) !== '') {
                        \mod_wordcards\task\translate_custom_sentence::queue_for_term($data->id);
                    }
                }
            }
            if ($data->id) {
                $ret->error = false;
            }

            // audio data
            if (!empty($data->audio_filemanager)) {
                $data = file_postupdate_standard_filemanager(
                    $data,
                    'audio',
                    $audiooptions,
                    $context,
                    constants::M_COMPONENT,
                    'audio',
                    $data->id
                );
                $data->audioversion = time();
                $needsupdating = true;

                // in the case a user has deleted all files, we will still have the draftid in the audio column, we want to set it to 0
                $fs = get_file_storage();
                $areafiles = $fs->get_area_files($context->id, 'mod_wordcards', 'audio', $data->id);
                if (!$areafiles || count($areafiles) == 0) {
                    $data->audio = '';
                } else if (count($areafiles) == 1) {
                    $file = array_pop($areafiles);
                    if ($file->is_directory()) {
                        $data->audio = '';
                    }
                }

            }

            // model sentence audio data
            if (!empty($data->model_sentence_audio_filemanager)) {
                // $data->audio_filemanager = $audioitemid;
                $data = file_postupdate_standard_filemanager(
                    $data,
                    'model_sentence_audio',
                    $audiooptions,
                    $context,
                    constants::M_COMPONENT,
                    'model_sentence_audio',
                    $data->id
                );
                $data->modelaudioversion = time();
                $needsupdating = true;
                // in the case a user has deleted all files, we will still have the draftid in the audio column, we want to set it to 0
                $fs = get_file_storage();
                $areafiles = $fs->get_area_files($context->id, 'mod_wordcards', 'model_sentence_audio', $data->id);

                if (!$areafiles || count($areafiles) == 0) {
                    $data->model_sentence_audio = '';
                } else if (count($areafiles) == 1) {
                    $file = array_pop($areafiles);
                    if ($file->is_directory()) {
                        $data->model_sentence_audio = '';
                    }
                }

            }

            if (!empty($data->image_filemanager)) {
                $data = file_postupdate_standard_filemanager(
                    $data,
                    'image',
                    $imageoptions,
                    $context,
                    constants::M_COMPONENT,
                    'image',
                    $data->id
                );
                $data->imageversion = time();
                $needsupdating = true;

                // in the case a user has deleted all files, we will still have the draftid in the image column, we want to set it to ''
                $fs = get_file_storage();
                $areafiles = $fs->get_area_files($context->id, 'mod_wordcards', 'image', $data->id);
                if (!$areafiles || count($areafiles) == 0) {
                    $data->image = '';
                } else if (count($areafiles) == 1) {
                    $file = array_pop($areafiles);
                    if ($file->is_directory()) {
                        $data->image = '';
                    }
                }
            }

            // lets update the passage hash here before we save the item in db
            if ($needsupdating) {
                if ($DB->update_record(constants::M_TERMSTABLE, $data)) {
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

    public static function submit_mform_returns()
    {
        return new external_value(PARAM_RAW);
        // return new external_value(PARAM_INT, 'group id');
    }

    /**
     * Parameters for sync_sentence_translations.
     *
     * @return external_function_parameters
     */
    public static function sync_sentence_translations_parameters() {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'The course module id'),
            ]
        );
    }

    /**
     * Fetch the translated example sentences for terms that do not have them.
     *
     * The work is in \mod_wordcards\sentence_sync, which an adhoc task and a cli script also call.
     * This is the path for somebody pressing a button and waiting for an answer.
     *
     * @param int $cmid
     * @return array counts, and a sentence saying what happened
     */
    public static function sync_sentence_translations($cmid) {
        $params = self::validate_parameters(self::sync_sentence_translations_parameters(), compact('cmid'));
        $cmid = $params['cmid'];

        $mod = mod_wordcards_module::get_by_cmid($cmid);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        // One dictionary lookup per term that needs one, so a large word list takes a while.
        \core_php_time_limit::raise(300);
        session_write_close();

        $counts = sentence_sync::sync_module($mod->get_id());

        // A term nothing matched has a sentence of its own, so no amount of looking it up will
        // help. Queue those to be translated directly instead - the alternative is a teacher
        // seeing the same unmatched count on every press with no way to act on it. Queueing is
        // deduplicated per term, so pressing twice does not double up.
        foreach ($counts['unmatchedids'] as $termid) {
            \mod_wordcards\task\translate_custom_sentence::queue_for_term($termid);
        }

        $counts['queued'] = count($counts['unmatchedids']);
        $message = get_string('ww_syncsentences_result', constants::M_COMPONENT, (object)$counts);
        if ($counts['queued'] > 0) {
            $message .= ' ' . get_string('ww_syncsentences_queued', constants::M_COMPONENT, $counts['queued']);
        }

        return [
            'updated' => $counts['updated'],
            'alreadyhave' => $counts['alreadyhave'],
            'nodata' => $counts['nodata'],
            'unmatched' => $counts['unmatched'],
            'queued' => $counts['queued'],
            'failed' => $counts['failed'],
            'message' => $message,
        ];
    }

    /**
     * What sync_sentence_translations returns.
     *
     * @return external_single_structure
     */
    public static function sync_sentence_translations_returns() {
        return new external_single_structure(
            [
                'updated' => new external_value(PARAM_INT, 'Terms that now have translated sentences'),
                'alreadyhave' => new external_value(PARAM_INT, 'Terms that already had them'),
                'nodata' => new external_value(PARAM_INT, 'Terms whose sense the dictionary has not translated yet'),
                'unmatched' => new external_value(PARAM_INT, 'Terms whose example sentence matched no sense'),
                'queued' => new external_value(PARAM_INT, 'Terms queued to have their own sentence translated'),
                'failed' => new external_value(PARAM_INT, 'Terms whose lookup failed'),
                'message' => new external_value(PARAM_TEXT, 'A sentence describing the result'),
            ]
        );
    }

    public static function search_dictionary_parameters()
    {
        return new external_function_parameters(
            [
                'terms' => new external_value(PARAM_RAW, 'The csv word list'),
                'cmid' => new external_value(PARAM_INT, 'The cmid'),
                'sourcelang' => new external_value(PARAM_TEXT, 'The language searched'),
                'targetlangs' => new external_value(PARAM_TEXT, 'The csv translation langs'),
            ]
        );

    }
    public static function search_dictionary($terms, $cmid, $sourcelang, $targetlangs)
    {
        $params = self::validate_parameters(
            self::search_dictionary_parameters(),
            compact('terms', 'cmid', 'sourcelang', 'targetlangs')
        );

        $mod = mod_wordcards_module::get_by_cmid($params['cmid']);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        $ret = new \stdClass();

        // We need to do this so that search_dictionary requests can run in parallel.
        session_write_close();

        // The wizard asks for one definitions language, which is the one the senses are read for.
        $deflang = trim(explode(',', $params['targetlangs'])[0]);
        $entries = word_adder::lookup($params['terms'], $params['sourcelang'], $deflang, $error);
        if ($entries === null) {
            $ret->success = false;
            $ret->payload = "unable to fetch dictionary entries: " . $error;
        } else {
            // Say which words are already in the set, so the wizard does not offer to add them again.
            foreach ($entries as $i => $entry) {
                $entries[$i]['alreadyinset'] = utils::term_exists($mod->get_id(), $entry['term']);
            }
            $ret->success = true;
            $ret->payload = json_encode($entries);
        }
        return $ret;
    }

    public static function search_dictionary_returns()
    {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_INT, 'Indicates success or failure of the call'),
                'payload' => new external_value(
                    PARAM_RAW,
                    'If call failed, contains a message about why. Else a json list of words, each with its senses'
                ),
            ]
        );
    }


    public static function set_my_words_parameters()
    {
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
    public static function set_my_words(int $termid, bool $newstatus)
    {
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

    public static function set_my_words_returns()
    {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_INT, 'Indicates success or failure of the call'),
                'newStatus' => new external_value(PARAM_BOOL, 'Indicates new status of the word'),
            ]
        );
    }

    public static function report_successful_learnclaim_parameters()
    {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT),
        ]);
    }

    public static function report_successful_learnclaim($termid)
    {
        global $DB;

        $params = self::validate_parameters(self::report_successful_association_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record(constants::M_TERMSTABLE, ['id' => $termid], '*', MUST_EXIST);
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

    public static function report_successful_learnclaim_returns()
    {
        return new external_value(PARAM_BOOL);
    }

    public static function set_user_preference_parameters()
    {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'The user preference name'),
            'value' => new external_value(PARAM_TEXT, 'The user preference value'),
        ]);
    }

    public static function set_user_preference($name, $value)
    {

        //set the user preference
        switch ($name) {
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

    public static function set_user_preference_returns()
    {
        return new external_value(PARAM_BOOL);
    }

    /**
     * Parameters for queue_words.
     *
     * @return external_function_parameters
     */
    public static function queue_words_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
            'words' => new external_multiple_structure(new external_value(PARAM_TEXT, 'A word to add')),
            'deflang' => new external_value(PARAM_ALPHANUMEXT, 'The language the definitions should be in'),
            'withimages' => new external_value(PARAM_BOOL, 'Also make a picture for each word', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Queue words to be looked up and added in the background, the first good meaning of each.
     *
     * @param int $cmid
     * @param array $words
     * @param string $deflang
     * @param bool $withimages
     * @return array
     */
    public static function queue_words($cmid, $words, $deflang, $withimages = false) {
        $params = self::validate_parameters(self::queue_words_parameters(), compact('cmid', 'words', 'deflang', 'withimages'));

        $mod = mod_wordcards_module::get_by_cmid($params['cmid']);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        // Only a language a lookup can return, as the wizard's own list offers.
        $sourcelang = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);
        if (!in_array($params['deflang'], utils::get_lookup_deflangs($sourcelang))) {
            throw new invalid_parameter_exception('The dictionary cannot give definitions in ' . $params['deflang']);
        }

        $result = background::queue_words($mod, $params['words'], $params['deflang'], $params['withimages']);
        return $result + ['pending' => background::pending($mod->get_id())];
    }

    /**
     * What queue_words returns.
     *
     * @return external_single_structure
     */
    public static function queue_words_returns() {
        return new external_single_structure([
            'queued' => new external_value(PARAM_INT, 'How many words were queued'),
            'alreadyinset' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A word left out because it is in the activity already')
            ),
            'pending' => self::pending_structure(),
        ]);
    }

    /**
     * Parameters for queue_images.
     *
     * @return external_function_parameters
     */
    public static function queue_images_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
        ]);
    }

    /**
     * Queue a picture to be made in the background for every word in the activity that has none.
     *
     * @param int $cmid
     * @return array
     */
    public static function queue_images($cmid) {
        $params = self::validate_parameters(self::queue_images_parameters(), compact('cmid'));

        $mod = mod_wordcards_module::get_by_cmid($params['cmid']);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        \mod_wordcards\task\generate_word_images::queue_all($mod->get_id());
        return ['pending' => background::pending($mod->get_id())];
    }

    /**
     * What queue_images returns.
     *
     * @return external_single_structure
     */
    public static function queue_images_returns() {
        return new external_single_structure([
            'pending' => self::pending_structure(),
        ]);
    }

    /**
     * Parameters for get_background_status.
     *
     * @return external_function_parameters
     */
    public static function get_background_status_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
        ]);
    }

    /**
     * What is still to be done in the background for an activity.
     *
     * @param int $cmid
     * @return array
     */
    public static function get_background_status($cmid) {
        $params = self::validate_parameters(self::get_background_status_parameters(), compact('cmid'));

        $mod = mod_wordcards_module::get_by_cmid($params['cmid']);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        return background::pending($mod->get_id());
    }

    /**
     * What get_background_status returns.
     *
     * @return external_single_structure
     */
    public static function get_background_status_returns() {
        return self::pending_structure();
    }

    /**
     * The shape of background::pending().
     *
     * @return external_single_structure
     */
    protected static function pending_structure() {
        return new external_single_structure([
            'words' => new external_value(PARAM_INT, 'Words still to be looked up and added'),
            'images' => new external_value(PARAM_INT, 'Batches of pictures still to be made'),
            'retrying' => new external_value(PARAM_INT, 'Word lookups that failed and will be tried again'),
        ]);
    }

    // Image Generation Parameters
    public static function generate_bulk_images_parameters()
    {
        return new external_function_parameters(
            [
                'cmid' => new external_value(PARAM_INT, 'The context module ID'),
            ]
        );
    }

    // Image Generation Function
    public static function generate_bulk_images($cmid)
    {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(
            self::generate_bulk_images_parameters(),
            ['cmid' => $cmid]
        );
        $mod = mod_wordcards_module::get_by_cmid($params['cmid']);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        // Make a picture for each term that has none.
        $imagegen = new imagegen($mod);
        $imageurls = $imagegen->generate_for_terms($mod->get_term_records());

        return ['images' => $imageurls];
    }
    public static function generate_bulk_images_returns()
    {
        // return array  of image urls with termids
        return new external_single_structure(
            [
                'images' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'url' => new external_value(PARAM_URL, 'The URL of the draft file'),
                            'termid' => new external_value(PARAM_INT, 'The term ID'),
                        ]
                    )
                )
            ]
        );
    }

    // Image Generation Parameters
    public static function generate_image_parameters()
    {
        return new external_function_parameters(
            [
                'termid' => new external_value(PARAM_INT, 'The term ID'),
                'prompt' => new external_value(PARAM_TEXT, 'The prompt for image generation'),
            ]
        );
    }

    // Image Generation Function
    public static function generate_image($termid, $prompt)
    {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(
            self::generate_image_parameters(),
            ['termid' => $termid, 'prompt' => $prompt]
        );

        $term = $DB->get_record(constants::M_TERMSTABLE, ['id' => $params['termid']], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());
        $mod->require_manage();

        // Make image generator
        $imagegen = new imagegen($mod);
        $imagedata = $imagegen->generate_image($params['termid'], $params['prompt']);
        if (!$imagedata) {
            throw new invalid_parameter_exception('Invalid generation failed');
        }

        return $imagedata;
    }
    public static function generate_image_returns()
    {
        return new external_single_structure(
            [
                'drafturl' => new external_value(PARAM_URL, 'The URL of the draft file'),
                'draftitemid' => new external_value(PARAM_INT, 'The draft item ID'),
                'termid' => new external_value(PARAM_INT, 'The term ID'),
                'error' => new external_value(PARAM_BOOL, 'Indicates if there was an error in generation'),
            ]
        );
    }

}
