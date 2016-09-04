<?php
/**
 * Lib.
 *
 * @package mod_flashcards
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
function flashcards_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return false;
    }
}

function flashcards_add_instance(stdClass $module, mod_flashcards_mod_form $mform = null) {
    global $DB;

    $module->timecreated = time();
    $module->timemodified = time();

    if (empty($module->skipglobal)) {
        $module->skipglobal = 0;
    }

    $module->finishedscattermsg = $module->finishedscattermsg_editor['text'];
    $module->completedmsg = $module->completedmsg_editor['text'];

    $module->id = $DB->insert_record('flashcards', $module);

    return $module->id;
}

function flashcards_update_instance(stdClass $module, mod_flashcards_mod_form $mform = null) {
    global $DB;

    $module->timemodified = time();
    $module->id = $module->instance;

    if (empty($module->skipglobal)) {
        $module->skipglobal = 0;
    }

    $module->finishedscattermsg = $module->finishedscattermsg_editor['text'];
    $module->completedmsg = $module->completedmsg_editor['text'];

    return $DB->update_record('flashcards', $module);
}

function flashcards_delete_instance($modid) {
    global $DB;

    $mod = mod_flashcards_module::get_by_modid($modid);
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
function flashcards_get_completion_state($course, $cm, $userid, $type) {
    global $CFG;

    $mod = mod_flashcards_module::get_by_cmid($cm->id);
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
function flashcards_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'flashcardsheader', get_string('modulenameplural', 'flashcards'));
    $mform->addElement('checkbox', 'reset_flashcard', get_string('deleteallentries','flashcards'));
}

/**
 * Course reset form defaults.
 * @return array
 */
function flashcards_reset_course_form_defaults($course) {
    return array('reset_flashcard'=>0);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * flashcards user data for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function flashcards_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'flashcards');
    $status = array();

    if (!empty($data->reset_flashcard)) {

        // Find all flashcards of the course.
        $flashcards = $DB->get_fieldset_select('flashcards', 'id', 'course = :course', array('course' => $data->courseid));
        error_log(print_r('FLASHCARD IDS TO DELETE USER DATA', true));
        error_log(print_r($flashcards, true));
        list($termssql, $termsparams) = $DB->get_in_or_equal($flashcards, SQL_PARAMS_NAMED);
        error_log(print_r($termssql, true));
        error_log(print_r($termsparams, true));

        // Retrieve the terms.
        $terms = $DB->get_fieldset_select('flashcards_terms', 'id', 'modid ' . $termssql, $termsparams);
        error_log(print_r('TERM IDS TO DELETE USER DATA', true));
        error_log(print_r($terms, true));
        list($sql, $params) = $DB->get_in_or_equal($terms, SQL_PARAMS_NAMED);
        error_log(print_r($sql, true));
        error_log(print_r($params, true));

        $DB->delete_records_select('flashcards_associations', 'termid ' . $sql, $params);
        $DB->delete_records_list('flashcards_progress', 'modid', $flashcards);
        $DB->delete_records_select('flashcards_seen', 'termid ' . $sql, $params);

        $status[] = array('component' => $componentstr, 'item' => get_string('removeuserdata', 'flashcards'), 'error' => false);
    }

    // PS: No flashcards date fields need to be shifted (i.e. need to be modified because the course start/end date changed)

    return $status;
}