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
                $ret = $record->learned == 1 ? get_string('yes') : get_string('no');
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

        // Empty data just in case we have no results
        $emptydata = array();


        $allsql= "SELECT t.*, a.successcount, m.learnpoint, a.selfclaim as selfclaim 
              FROM {wordcards_associations} a
              INNER JOIN {wordcards_terms} t
              INNER JOIN {wordcards} m
                ON a.termid = t.id
                AND m.id = t.modid
                AND t.deleted = 0
              WHERE  a.userid = ?
              AND m.course = ?";

        $alldata = $DB->get_records_sql($allsql,  [$formdata->userid, (int)$mod->get_course()->id]);
        if ($alldata) {
            foreach ($alldata as $thedata) {
                //calculate the learned progress
                $thedata->learned = $thedata->successcount >= $thedata->learnpoint ? true : false;
                if($thedata->learned ) {
                    $thedata->learned_progress = 100;
                }else{
                    $thedata->learned_progress = round($thedata->successcount / $thedata->learnpoint * 100);
                }

                $this->rawdata[] = $thedata;
            }
            $this->rawdata = $alldata;
        } else {
            $this->rawdata = $emptydata;
        }
        return true;

    }

}