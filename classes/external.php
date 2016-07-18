<?php
/**
 * External.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

/**
 * External class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_external extends external_api {

    public static function mark_as_seen_parameters() {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT)
        ]);
    }

    public static function mark_as_seen($termid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::mark_as_seen_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record('flashcards_terms', ['id' => $termid], '*', MUST_EXIST);
        $mod = mod_flashcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());

        // We do not log the completion for teachers.
        if ($mod->can_manage()) {
            return true;
        }

        // Require view and make sure the user did not previously mark as seen.
        $params = ['userid' => $USER->id, 'termid' => $termid];
        $mod->require_view();
        if ($DB->record_exists('flashcards_seen', $params)) {
            return true;
        }

        $record = (object) $params;
        $record->timecreated = time();
        $DB->insert_record('flashcards_seen', $record);

        return true;
    }

    public static function mark_as_seen_returns() {
        return new external_value(PARAM_BOOL);
    }
}
