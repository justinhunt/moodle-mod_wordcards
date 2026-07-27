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

class sitelearned extends basereport {

    protected $report = "sitelearned";
    protected $fields = array('username', 'termslearned', 'learned_p', 'selfclaimed');
    protected $headingdata = null;
    protected $qcache = array();
    protected $ucache = array();
    protected $modid = 0;

    public function fetch_formatted_field($field, $record, $withlinks) {
        global $DB, $CFG, $OUTPUT;
        switch ($field) {

            case 'username':
                $user = $this->fetch_cache('user', $record->userid);
                $usersname = fullname($user);
                if ($withlinks) {
                    $url = new \moodle_url(constants::M_URL . '/reports.php',
                        array('report' => 'siteuserlearned', 'n' => $this->modid, 'userid'=>$record->userid));
                    $ret = "<a href='" . $url->out() . "'>". $usersname . "</a>" ;
                }else{
                    $ret =  $usersname;
                }
                break;

            case 'termslearned':
                $ret = $record->termslearned;
                break;

            case 'learned_p':
                if ($record->totalterms > 0) {
                    $ret = round($record->termslearned / $record->totalterms * 100);
                } else {
                    $ret = 0;
                }
                break;

            case 'selfclaimed':
                $ret = $record->selfclaimed;
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
        return get_string('sitelearnedheading', constants::M_COMPONENT);
    }

    public function process_raw_data($formdata) {
        global $DB, $USER;

        //heading data
        $this->headingdata = new \stdClass();
        $this->modid = $formdata->modid;
        $emptydata = array();

        //get total terms in the entire site
        $countsql = "SELECT COUNT(t.id) FROM {wordcards_terms} t
                     INNER JOIN {wordcards} m
                     ON t.modid = m.id
                     WHERE t.deleted = 0";
        $totalterms = $DB->count_records_sql($countsql);

        $allsql= "SELECT a.userid, COUNT( CASE WHEN a.successcount >= m.learnpoint THEN 1 END) as termslearned, SUM(a.selfclaim) as selfclaimed, $totalterms as totalterms 
              FROM {wordcards_associations} a
              INNER JOIN {wordcards_terms} t
              INNER JOIN {wordcards} m
                ON a.termid = t.id
                AND m.id = t.modid
                AND t.deleted = 0
              GROUP BY a.userid";

        $alldata = $DB->get_records_sql($allsql);

        if ($alldata) {
            foreach ($alldata as $thedata) {

                $this->rawdata[] = $thedata;
            }
            $this->rawdata = $alldata;
        } else {
            $this->rawdata = $emptydata;
        }
        return true;
    }

}
