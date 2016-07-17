<?php
/**
 * Lib.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supported features.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return mixed True if module supports feature, null if doesn't know.
 */
function flashcards_supports($feature) {
    switch ($feature) {
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return false;
    }
}

function flashcards_add_instance(stdClass $module, mod_flashcards_mod_form $mform = null) {
    global $DB;

    // No support for intro for now.
    $module->intro = '';
    $module->introformat = FORMAT_HTML;

    $module->timecreated = time();
    $module->timemodified = time();

    $module->id = $DB->insert_record('flashcards', $module);

    return $module->id;
}

function flashcards_update_instance(stdClass $module, mod_flashcards_mod_form $mform = null) {
    global $DB;

    // No support for intro for now.
    $module->intro = '';
    $module->introformat = FORMAT_HTML;

    $module->timemodified = time();
    $module->id = $module->instance;

    return $DB->update_record('flashcards', $module);
}

function flashcards_delete_instance($modid) {
    global $DB;

    if (!$DB->record_exists('flashcards', array('id' => $modid))) {
        return false;
    }

    $DB->delete_records('flashcards', array('id' => $modid));
    $DB->delete_records('flashcards_terms', array('modid' => $modid));

    return true;
}
