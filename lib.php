<?php
/**
 * Lib.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

// TODO Support activity completion.
defined('MOODLE_INTERNAL') || die();

/**
 * Supported features.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return mixed True if module supports feature, null if doesn't know.
 */
function wordcards_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        default:
            return false;
    }
}

function wordcards_add_instance(stdClass $module, mod_wordcards_mod_form $mform = null) {
    global $DB;

    $module->timecreated = time();
    $module->timemodified = time();

    if (empty($module->skipreview)) {
        $module->skipreview = 0;
    }

    $module->finishedstepmsg = $module->finishedstepmsg_editor['text'];
    $module->completedmsg = $module->completedmsg_editor['text'];
    $module->id = $DB->insert_record('wordcards', $module);

    return $module->id;
}

function wordcards_update_instance(stdClass $module, mod_wordcards_mod_form $mform = null) {
    global $DB;

    $module->timemodified = time();
    $module->id = $module->instance;

    if (empty($module->skipreview)) {
        $module->skipreview = 0;
    }

    $module->finishedstepmsg = $module->finishedstepmsg_editor['text'];
    $module->completedmsg = $module->completedmsg_editor['text'];

    return $DB->update_record('wordcards', $module);
}

function wordcards_delete_instance($modid) {
    global $DB;

    $mod = mod_wordcards_module::get_by_modid($modid);
    $mod->delete();

    return true;
}

/**
 * Obtains the completion state.
 *
 * @param object $course The course.
 * @param object $cm The course module.
 * @param int $userid The user ID.
 * @param bool $type Type of comparison (or/and).
 * @return bool True if completed, false if not, else $type.
 */
function wordcards_get_completion_state($course, $cm, $userid, $type) {
    global $CFG;

    $mod = mod_wordcards_module::get_by_cmid($cm->id);
    if ($mod->is_completion_enabled()) {
        return $mod->has_user_completed_activity($userid);
    }

    // Completion option is not enabled, we must return $type.
    return $type;
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the data.
 *
 * @param $mform form passed by reference
 */
function wordcards_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'wordcardsheader', get_string('modulenameplural', 'wordcards'));
    $mform->addElement('checkbox', 'reset_wordcard', get_string('deleteallentries','wordcards'));
}

/**
 * Course reset form defaults.
 * @return array
 */
function wordcards_reset_course_form_defaults($course) {
    return array('reset_wordcard'=>0);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * wordcards user data for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function wordcards_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'wordcards');
    $status = array();

    if (!empty($data->reset_wordcard)) {

        // Find all wordcards of the course.
        $wordcards = $DB->get_fieldset_select('wordcards', 'id', 'course = :course', array('course' => $data->courseid));
        list($termssql, $termsparams) = $DB->get_in_or_equal($wordcards, SQL_PARAMS_NAMED);

        // Retrieve the terms.
        $terms = $DB->get_fieldset_select('wordcards_terms', 'id', 'modid ' . $termssql, $termsparams);
        list($sql, $params) = $DB->get_in_or_equal($terms, SQL_PARAMS_NAMED);

        $DB->delete_records_select('wordcards_associations', 'termid ' . $sql, $params);
        $DB->delete_records_list('wordcards_progress', 'modid', $wordcards);
        $DB->delete_records_select('wordcards_seen', 'termid ' . $sql, $params);

        $status[] = array('component' => $componentstr, 'item' => get_string('removeuserdata', 'wordcards'), 'error' => false);
    }

    // PS: No wordcards date fields need to be shifted (i.e. need to be modified because the course start/end date changed)

    return $status;
}

/**
 * Serves the files from the  file areas
 *
 * @package mod_tquiz
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the tquiz's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function wordcards_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    if ($filearea === 'audio' or $filearea === 'image') {

        $itemid = (int) array_shift($args);

        require_course_login($course, true, $cm);

        if (!has_capability('mod/wordcards:view', $context)) {
            return false;
        }

        $fs = get_file_storage();
        $areafiles = $fs->get_area_files($context->id,'mod_wordcards',$filearea,$itemid);
        if($areafiles){
            $file = array_pop($areafiles);
            if($file->is_directory()){
                if($areafiles) {
                    $file = array_pop($areafiles);
                }
            }
            // finally send the file
            if($file && !$file->is_directory()) {
                send_stored_file($file, null, 0, $forcedownload, $options);
            }
        }
    }
    return false;
}


////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding readseed nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the readseed module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function wordcards_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the readseed settings
 *
 * This function is called when the context for the page is a readseed module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $readseednode {@link navigation_node}
 */
function wordcards_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $readseednode=null) {
}
