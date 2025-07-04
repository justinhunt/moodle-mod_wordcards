<?php

namespace mod_wordcards\local\report;

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 20:52
 */


use \mod_wordcards\constants;
use \mod_wordcards\utils;

class courseuserlearned extends basereport {

    protected $report = "courseuserlearned";
    protected $fields = array('term', 'learned', 'learned_progress', 'selfclaim');
    protected $headingdata = null;
    protected $qcache = array();
    protected $ucache = array();

    public function fetch_formatted_field($field, $record, $withlinks) {
        global $DB, $CFG, $OUTPUT;
        switch ($field) {
            case 'id':
                $ret = $record->id;
                break;

            case 'username':
                $user = $this->fetch_cache('user', $record->userid);
                $ret = fullname($user);
                break;

            case 'term':
                $ret = $record->term;
                break;

            case 'learned':
                $ret = $record->learned ? get_string('yes') : get_string('no');
                break;

            case 'learned_progress':
                $ret = $record->learned_progress .'%';
                break;

            case 'selfclaim':
                $ret = $record->selfclaim ? get_string('yes') : get_string('no');
                break;

            default:
                if (property_exists($record, $field)) {
                    $ret = $record->{$field};
                } else {
                    $ret = '';
                }
        }
        return $ret;
    }

    public function fetch_formatted_heading() {

        $record = $this->headingdata;
        $ret='';
        if(!$record){return $ret;}
        $user = $this->fetch_cache('user',$record->userid);
        $a = new \stdClass();
        $a->username = fullname($user);
        $a->coursename = $record->course->fullname;
        return get_string('courseuserlearnedheading',constants::M_COMPONENT,$a);
    }

    public function process_raw_data($formdata) {
        global $DB;

        //module for learned status (same code also used for defs page)
        $mod = \mod_wordcards_module::get_by_cmid($formdata->cmid);

        //heading data
        $this->headingdata = new \stdClass();
        $this->headingdata->userid = $formdata->userid;
        $this->headingdata->course = $mod->get_course();

        //get all wordcards in course
        $wordcardsids = $DB->get_fieldset_select(constants::M_TABLE, 'id', 'course = ?', array($mod->get_course()->id));
        list($wordcardswhere, $allwordcardsparams) = $DB->get_in_or_equal($wordcardsids);

        //get terms
        $termsselect = "SELECT t.* 
            FROM {".constants::M_TERMSTABLE."} t
            WHERE t.deleted = 0 AND t.modid $wordcardswhere";
        $terms =  $DB->get_records_sql($termsselect, $allwordcardsparams);

        //add user learned status
        $terms=$mod->insert_learned_state($terms,$formdata->userid);
        usort($terms, fn($a, $b) => strcmp($a->learned_progress, $b->learned_progress));
        $this->rawdata = $terms;
        return true;
    }

}