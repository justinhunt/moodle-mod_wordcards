<?php
/**
 * Helper.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

use mod_wordcards\utils;

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

        $tablabel = utils::fetch_activity_tablabel($mod->get_practicetype(mod_wordcards_module::STATE_STEP1));
        $tabicon = utils::fetch_activity_tabicon($mod->get_practicetype(mod_wordcards_module::STATE_STEP1));
        $tabs = [
            new tabobject(mod_wordcards_module::STATE_TERMS,
                new moodle_url('/mod/wordcards/view.php', ['id' => $cmid]),
                get_string('tabdefinitions', 'mod_wordcards'), 'fa-dot-circle-o', true),

            new tabobject(mod_wordcards_module::STATE_STEP1,
                new moodle_url('/mod/wordcards/activity.php', ['id' => $cmid, 'nextstep'=>mod_wordcards_module::STATE_STEP1]),
                $tablabel, $tabicon, true)
        ];

        if($mod->get_mod()-> {mod_wordcards_module::STATE_STEP2} != mod_wordcards_module::PRACTICETYPE_NONE){
            $tablabel = utils::fetch_activity_tablabel($mod->get_practicetype(mod_wordcards_module::STATE_STEP2));
            $tabicon = utils::fetch_activity_tabicon($mod->get_practicetype(mod_wordcards_module::STATE_STEP2));
            $tabs[]= new tabobject(mod_wordcards_module::STATE_STEP2,
                new moodle_url('/mod/wordcards/activity.php', ['id' => $cmid, 'nextstep' =>mod_wordcards_module::STATE_STEP2]),
                $tablabel, $tabicon, true);
        }

        if($mod->get_mod()->{mod_wordcards_module::STATE_STEP3} != mod_wordcards_module::PRACTICETYPE_NONE) {
            $tablabel = utils::fetch_activity_tablabel($mod->get_practicetype(mod_wordcards_module::STATE_STEP3));
            $tabicon = utils::fetch_activity_tabicon($mod->get_practicetype(mod_wordcards_module::STATE_STEP3));
            $tabs[]= new tabobject(mod_wordcards_module::STATE_STEP3,
                    new moodle_url('/mod/wordcards/activity.php', ['id' => $cmid, 'nextstep' => mod_wordcards_module::STATE_STEP3]),
                    $tablabel, $tabicon, true);
        }


        if($mod->get_mod()->{mod_wordcards_module::STATE_STEP4} != mod_wordcards_module::PRACTICETYPE_NONE) {
            $tablabel = utils::fetch_activity_tablabel($mod->get_practicetype(mod_wordcards_module::STATE_STEP4));
            $tabicon = utils::fetch_activity_tabicon($mod->get_practicetype(mod_wordcards_module::STATE_STEP4));
            $tabs[]=  new tabobject(mod_wordcards_module::STATE_STEP4,
                    new moodle_url('/mod/wordcards/activity.php', ['id' => $cmid, 'nextstep' =>mod_wordcards_module::STATE_STEP4]),
                    $tablabel, $tabicon, true);
        }

        if($mod->get_mod()->{mod_wordcards_module::STATE_STEP5} != mod_wordcards_module::PRACTICETYPE_NONE) {
            $tablabel = utils::fetch_activity_tablabel($mod->get_practicetype(mod_wordcards_module::STATE_STEP5));
            $tabicon = utils::fetch_activity_tabicon($mod->get_practicetype(mod_wordcards_module::STATE_STEP5));
            $tabs[]=  new tabobject(mod_wordcards_module::STATE_STEP5,
                    new moodle_url('/mod/wordcards/activity.php', ['id' => $cmid, 'nextstep' => mod_wordcards_module::STATE_STEP5]),
                    $tablabel, $tabicon, true);
        }


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
