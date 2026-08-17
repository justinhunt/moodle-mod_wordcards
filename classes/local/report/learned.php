<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Learned report.
 *
 * @package    mod_wordcards
 * @copyright  2020 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_wordcards\local\report;

use \mod_wordcards\constants;
use \mod_wordcards\utils;

class learned extends basereport {

    protected $report = "learned";
    protected $fields = array('username', 'termslearned', 'learned_p');
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
                        array('report' => 'userlearned', 'n' => $record->modid, 'userid'=>$record->userid));
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
        return get_string('learnedheading', constants::M_COMPONENT);

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

        $groupsmode = groups_get_activity_groupmode($cm,$course);
        $context = empty($cm) ? \context_course::instance($course->id) : \context_module::instance($cm->id);
        $supergrouper = has_capability('moodle/site:accessallgroups', $context, $USER->id);

        $totalterms = $DB->count_records(constants::M_TERMSTABLE, array('modid' => $formdata->modid, 'deleted' => 0));

        //if need to partition to groups, SQL for groups
        if($formdata->groupid > 0){

             list($groupswhere, $allparams) = $DB->get_in_or_equal($formdata->groupid);

            $allsql= "SELECT a.userid,t.modid,COUNT((CASE WHEN a.successcount >= $moduleinstance->learnpoint THEN 1 END)) as termslearned, $totalterms as totalterms 
                  FROM {wordcards_associations} a
                  INNER JOIN {wordcards_terms} t
                    ON a.termid = t.id
                   AND t.modid = ?
                   AND t.deleted = 0
                   INNER JOIN {groups_members} gm ON a.userid=gm.userid
                    WHERE gm.groupid $groupswhere
                    GROUP BY a.userid";

            array_unshift($allparams,$formdata->modid);
            $alldata = $DB->get_records_sql($allsql, $allparams);
        }else{

            $allsql= "SELECT a.userid,t.modid,COUNT((CASE WHEN a.successcount >=  $moduleinstance->learnpoint  THEN 1 END)) as termslearned, $totalterms as totalterms 
                  FROM {wordcards_associations} a
                  INNER JOIN {wordcards_terms} t
                    ON a.termid = t.id
                   AND t.modid = ?
                   AND t.deleted = 0
                    GROUP BY a.userid";
            $allparams =[];
            $allparams[]=$formdata->modid;

            $alldata = $DB->get_records_sql($allsql, $allparams);

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