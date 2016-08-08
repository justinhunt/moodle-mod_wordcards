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

    public static function get_tabs(mod_flashcards_module $mod, $current) {
        $cmid = $mod->get_cmid();
        $canmanage = $mod->can_manage();
        $inactives = array_diff(mod_flashcards_module::get_all_states(), $mod->get_allowed_states());

        $tabs = [
            new tabobject(mod_flashcards_module::STATE_TERMS,
                new moodle_url('/mod/flashcards/view.php', ['id' => $cmid]),
                get_string('tabdefinitions', 'mod_flashcards'), '', true),

            new tabobject(mod_flashcards_module::STATE_LOCAL,
                new moodle_url('/mod/flashcards/local.php', ['id' => $cmid]),
                get_string('tablocal', 'mod_flashcards'), '', true),

            new tabobject(mod_flashcards_module::STATE_GLOBAL,
                new moodle_url('/mod/flashcards/global.php', ['id' => $cmid]),
                get_string('tabglobal', 'mod_flashcards'), '', true),
        ];

        if ($canmanage) {
            $tabs[] = new tabobject('setup',
                new moodle_url('/mod/flashcards/setup.php', ['id' => $cmid]),
                get_string('tabsetup', 'mod_flashcards'), '', true);
                
            $tabs[] = new tabobject('import',
                new moodle_url('/mod/flashcards/import.php', ['id' => $cmid]),
                get_string('tabimport', 'mod_flashcards'), '', true);
        }

        return new tabtree($tabs, $current, $inactives);
    }

}
