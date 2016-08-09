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
