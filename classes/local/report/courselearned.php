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

class courselearned extends basereport {

    protected $report = "courselearned";
    protected $fields = array('username', 'termslearned', 'learned_p', 'selfclaimed');
    protected $headingdata = null;
    protected $qcache = array();
    protected $ucache = array();

    public function fetch_formatted_field($field, $record, $withlinks) {
        global $DB, $CFG, $OUTPUT;
        switch ($field) {

            case 'username':
                $user = $this->fetch_cache('user', $record->userid);
                $usersname = fullname($user);
                if ($withlinks) {
                    $url = new \moodle_url(constants::M_URL . '/reports.php',
                        array('report' => 'courseuserlearned', 'n' => $record->modid, 'userid'=>$record->userid));
                    $ret = "<a href='" . $url->out() . "'>". $usersname . "</a>" ;
                }else{
                    $ret =  $usersname;
                }
                break;

            case 'termslearned':
                $ret = $record->termslearned;
                break;

            case 'learned_p':
                $ret = round($record->termslearned / $record->totalterms * 100);
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
        $record = $this->headingdata;
        $ret = '';
        if (!$record) {
            return $ret;
        }
        return get_string('courselearnedheading', constants::M_COMPONENT, $record->course->fullname);

    }

    public function process_raw_data($formdata) {
        global $DB, $USER;

        //heading data
        $this->headingdata = new \stdClass();
        $emptydata = array();

        //groupsmode
        $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $formdata->modid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);

        // Save course information for later
        $this->headingdata->course = $course;

        $groupsmode = groups_get_activity_groupmode($cm,$course);
        $context = empty($cm) ? \context_course::instance($course->id) : \context_module::instance($cm->id);
        $supergrouper = has_capability('moodle/site:accessallgroups', $context, $USER->id);

        //get all wordcards in course
        $wordcardsids = $DB->get_fieldset_select(constants::M_TABLE, 'id', 'course = ?', array($course->id));
        list($wordcardswhere, $allwordcardsparams) = $DB->get_in_or_equal($wordcardsids);

        $countsql = "SELECT COUNT(t.id) FROM {wordcards_terms} t
                    WHERE t.modid $wordcardswhere 
                    AND t.deleted = 0";
        $totalterms = $DB->count_records_sql($countsql, $allwordcardsparams);

        //if need to partition to groups, SQL for groups
        if($formdata->groupid > 0){

             list($groupswhere, $allparams) = $DB->get_in_or_equal($formdata->groupid);

            $allsql= "SELECT DISTINCT a.userid,t.modid,COUNT( CASE WHEN a.successcount >= $moduleinstance->learnpoint THEN 1 END) as termslearned, SUM(a.selfclaim) as selfclaimed, $totalterms as totalterms 
                  FROM {wordcards_associations} a
                  INNER JOIN {wordcards_terms} t
                    ON a.termid = t.id
                   AND t.deleted = 0
                   INNER JOIN {groups_members} gm ON a.userid=gm.userid
                    WHERE gm.groupid $groupswhere AND t.modid $wordcardswhere
                    GROUP BY a.userid";

            $allparams = array_merge($allparams, $allwordcardsparams);
            $alldata = $DB->get_records_sql($allsql, $allparams);
        }else{

            $allsql= "SELECT DISTINCT a.userid,t.modid,COUNT( CASE WHEN a.successcount >=  $moduleinstance->learnpoint  THEN 1 END) as termslearned, SUM(a.selfclaim) as selfclaimed, $totalterms as totalterms 
                  FROM {wordcards_associations} a
                  INNER JOIN {wordcards_terms} t
                    ON a.termid = t.id
                   AND t.deleted = 0
                    WHERE t.modid $wordcardswhere
                    GROUP BY a.userid";

            $alldata = $DB->get_records_sql($allsql,  $allwordcardsparams);

        }

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