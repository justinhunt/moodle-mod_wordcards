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

class userlearned extends basereport {

    protected $report = "userlearned";
    protected $fields = array('term', 'learned', 'learned_progress');
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
        return get_string('userlearnedheading',constants::M_COMPONENT,fullname($user));


    }

    public function process_raw_data($formdata) {
        global $DB;

        //heading data
        $this->headingdata = new \stdClass();
        $this->headingdata->userid = $formdata->userid;
        //module for learned status (same code also used for defs page)
        $mod = \mod_wordcards_module::get_by_cmid($formdata->cmid);

        //get terms
        $params = ['modid' => $formdata->modid,'deleted' => 0];
        $terms = $DB->get_records(constants::M_TERMSTABLE, $params, 'id ASC');
        //add user learned status
        $terms=$mod->insert_learned_state($terms,$formdata->userid);
        usort($terms, fn($a, $b) => strcmp($a->learned_progress, $b->learned_progress));
        $this->rawdata = $terms;
        return true;
    }

}