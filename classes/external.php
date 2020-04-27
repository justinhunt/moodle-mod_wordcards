<?php
/**
 * External.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
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

    public static function check_by_phonetic_parameters(){
        return new external_function_parameters(
                 array('spoken' => new external_value(PARAM_TEXT, 'The spoken phrase'),
                       'correct' => new external_value(PARAM_TEXT, 'The correct phrase'),
                       'language' => new external_value(PARAM_TEXT, 'The language eg en-US')
                 )
        );

    }
    public static function check_by_phonetic($spoken, $correct, $language){
        $language = substr($language,0,2);
        $spokenphonetic = utils::convert_to_phonetic($spoken,$language);
        $correctphonetic = utils::convert_to_phonetic($correct,$language);
        $similar_percent = 0;
        $similar_chars = similar_text($correctphonetic,$spokenphonetic,$similar_percent);
        return round($similar_percent,0);

    }

    public static function check_by_phonetic_returns(){
        return new external_value(PARAM_INT,'how close is spoken to correct, 0 - 100');
    }

    public static function mark_as_seen_parameters() {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT)
        ]);
    }

    public static function mark_as_seen($termid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::mark_as_seen_parameters(), compact('termid'));
        extract($params);

        $term = $DB->get_record('wordcards_terms', ['id' => $termid], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());

        // We do not log the completion for teachers.
        if ($mod->can_manage()) {
            return true;
        }

        // Require view and make sure the user did not previously mark as seen.
        $params = ['userid' => $USER->id, 'termid' => $termid];
        $mod->require_view();
        if ($DB->record_exists('wordcards_seen', $params)) {
            return true;
        }

        $record = (object) $params;
        $record->timecreated = time();
        $DB->insert_record('wordcards_seen', $record);

        return true;
    }

    public static function mark_as_seen_returns() {
        return new external_value(PARAM_BOOL);
    }

    public static function report_successful_association_parameters() {
        return new external_function_parameters([
            'termid' => new external_value(PARAM_INT)
        ]);
    }

    public static function report_successful_association($termid) {
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
        ]);
    }

    public static function report_failed_association($term1id, $term2id) {
        global $DB;

        $params = self::validate_parameters(self::report_failed_association_parameters(), compact('term1id', 'term2id'));
        extract($params);

        $term = $DB->get_record('wordcards_terms', ['id' => $term1id], '*', MUST_EXIST);
        $mod = mod_wordcards_module::get_by_modid($term->modid);
        self::validate_context($mod->get_context());

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

    public static function report_step_grade($modid,$correct){
        $ret= utils::update_stepgrade($modid, $correct);
        return $ret;
    }
    public static function report_step_grade_returns() {
        return new external_value(PARAM_BOOL);
    }

}
