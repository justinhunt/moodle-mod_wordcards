<?php
/**
 * Helper.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

/**
 * Helper class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_helper {

    public static function get_tabs(mod_wordcards_module $mod, $current) {
        $cmid = $mod->get_cmid();
        $canmanage = $mod->can_manage();
        $inactives = array_diff(mod_wordcards_module::get_all_states(), $mod->get_allowed_states());

        $tabs = [
            new tabobject(mod_wordcards_module::STATE_TERMS,
                new moodle_url('/mod/wordcards/view.php', ['id' => $cmid]),
                get_string('tabdefinitions', 'mod_wordcards'), '', true),

            new tabobject(mod_wordcards_module::STATE_LOCAL,
                new moodle_url('/mod/wordcards/local.php', ['id' => $cmid]),
                get_string('tablocal', 'mod_wordcards'), '', true),

            new tabobject(mod_wordcards_module::STATE_GLOBAL,
                new moodle_url('/mod/wordcards/global.php', ['id' => $cmid]),
                get_string('tabglobal', 'mod_wordcards'), '', true),
        ];

        if ($canmanage) {
            $tabs[] = new tabobject('setup',
                new moodle_url('/mod/wordcards/setup.php', ['id' => $cmid]),
                get_string('tabsetup', 'mod_wordcards'), '', true);

            $tabs[] = new tabobject('import',
                new moodle_url('/mod/wordcards/import.php', ['id' => $cmid]),
                get_string('tabimport', 'mod_wordcards'), '', true);
        }

        return new tabtree($tabs, $current, $inactives);
    }

}
