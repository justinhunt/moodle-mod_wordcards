<?php
/**
 * Helper.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

/**
 * Helper class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_helper {

    public static function can_manage(context $context) {
        return  has_capability('mod/flashcards:addinstance', $context);
    }

    public static function require_manage(context $context) {
        require_capability('mod/flashcards:addinstance', $context);
    }

    public static function can_view(context $context) {
        return  has_capability('mod/flashcards:view', $context);
    }

    public static function require_view(context $context) {
        require_capability('mod/flashcards:view', $context);
    }

    public static function get_definitions($modid, $includedeleted = false) {
        global $DB;
        $params = ['modid' => $modid];
        if (!$includedeleted) {
            $params['deleted'] = 0;
        }
        return $DB->get_records('flashcards_terms', $params, 'id ASC');
    }

    public static function get_definitions_seen($modid) {
        global $DB, $USER;

        $sql = 'SELECT s.*
                  FROM {flashcards_seen} s
                  JOIN {flashcards_terms} t
                    ON s.termid = t.id
                 WHERE t.modid = ?
                   AND s.userid = ?';

        return $DB->get_records_sql($sql, [$modid, $USER->id]);
    }

    public static function get_tabs($context, $current) {
        $cmid = $context->instanceid;
        $canmanage = self::can_manage($context);
        $inactives = [];

        if (!$canmanage) {
            $inactives = ['local', 'global'];
        }

        $tabs = [
            new tabobject('definitions',
                new moodle_url('/mod/flashcards/view.php', ['id' => $cmid]),
                get_string('tabdefinitions', 'mod_flashcards'), '', true),

            new tabobject('local',
                new moodle_url('/mod/flashcards/local.php', ['id' => $cmid]),
                get_string('tablocal', 'mod_flashcards'), '', true),

            new tabobject('global',
                new moodle_url('/mod/flashcards/global.php', ['id' => $cmid]),
                get_string('tabglobal', 'mod_flashcards'), '', true),
        ];

        if ($canmanage) {
            $tabs[] = new tabobject('setup',
                new moodle_url('/mod/flashcards/setup.php', ['id' => $cmid]),
                get_string('tabsetup', 'mod_flashcards'), '', true);
        }

        return new tabtree($tabs, $current, $inactives);
    }

}
