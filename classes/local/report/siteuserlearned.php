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
 * Site user learned report.
 *
 * @package    mod_wordcards
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_wordcards\local\report;

use \mod_wordcards\constants;
use \mod_wordcards\utils;

class siteuserlearned extends basereport {

    protected $report = "siteuserlearned";
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
        return get_string('siteuserlearnedheading',constants::M_COMPONENT,$a);
    }

    public function process_raw_data($formdata) {
        global $DB;

        //heading data
        $this->headingdata = new \stdClass();
        $this->headingdata->userid = $formdata->userid;

        // Empty data just in case we have no results
        $emptydata = array();


        $allsql= "SELECT t.*, a.successcount, m.learnpoint, a.selfclaim as selfclaim 
              FROM {wordcards_associations} a
              INNER JOIN {wordcards_terms} t
              INNER JOIN {wordcards} m
                ON a.termid = t.id
                AND m.id = t.modid
                AND t.deleted = 0
              WHERE  a.userid = ?";

        $alldata = $DB->get_records_sql($allsql,  [$formdata->userid]);
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
