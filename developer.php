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
 * Developer tools for readaloud
 *
 *
 * @package    mod_readaloud
 * @copyright  2021 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

use \mod_wordcards\constants;
use \mod_wordcards\utils;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // WordCards instance ID

$action = optional_param('action', 'none', PARAM_TEXT); // report type




if ($id) {
    $cm         = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(0,'You must specify a course_module ID or an instance ID');
}

$PAGE->set_url(constants::M_URL . '/developer.php',
	array('id' => $cm->id,'action'=>$action));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/wordcards:manage', $modulecontext);

//Get an admin settings 
$config = get_config(constants::M_COMPONENT);

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('course');
$PAGE->requires->jquery();

//This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
$mode = 'view';
$header = $renderer->header($moduleinstance, $cm, $mode, null, get_string('developer', constants::M_COMPONENT));

//Process Actions.
switch ($action){
	case 'generatedata':

        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE,
            array('modid'=>  $moduleinstance->id),'timecreated DESC','*',0,1);
	    if(!$attempts){
            echo $header;
	        echo '<h3>No attempt to generate data from</h3>';
            echo $renderer->footer();
            return;
        }
        $latestattempt = array_shift($attempts);

        //terms
        $terms = $DB->get_records(constants::M_TERMSTABLE,array('modid'=>$moduleinstance->id));

        //assocs
        $allsql= "SELECT a.* 
                  FROM {wordcards_associations} a
                  INNER JOIN {wordcards_terms} t
                    ON a.termid = t.id
                   AND t.modid = ?
                   AND t.deleted = 0
                   WHERE a.userid = ?";

        $assocs = $DB->get_records_sql($allsql, [$moduleinstance->id,$latestattempt->userid]);
        //TO DO
        // - create a seen and assocs for random terms that match those of the assocs of the current user

        $users = get_enrolled_users($modulecontext);
        //reindex array
        $users = array_values($users);
        $created = 0;
        for($x=0;$x<count($users);$x++){
            $success = copyAttempt($latestattempt,$assocs,$terms, $users[$x]);
            if($success){$created++;}

        }//end of user loop
        redirect(new \moodle_url(constants::M_URL . '/developer.php',
            array('id' => $cm->id)),'Created Attempts:' . $created,5);

		return;

	case 'none':
    default:
}

//output the page
echo $header;

echo "<div>Generate random attempts from the last attempt in the table, 1 for each enrolled user</div>";
$sb= new \single_button(
    new \moodle_url(constants::M_URL . '/developer.php', array('action' => 'generatedata', 'id' => $cm->id, 'n' => $moduleinstance->id)),
    "Generate Attempt Data", 'get');
echo $OUTPUT->render($sb);

echo $renderer->footer();


function getRandomSubset($array, $count)
{
    //if our array is not big enough, bad ..

    if(count($array)<$count){return $array;}

    $keys = array_rand($array, $count); // Get random keys from the original array
    $subset = array(); // Initialize an empty array to store the subset

    // Create the subset array by adding elements corresponding to random keys
    foreach ($keys as $key) {
        $subset[$key] = $array[$key];
    }

    return $subset;
}


function copyAttempt($attempt,$assocs,$terms,  $user ){

    global $DB;
    $newatt = $attempt;

    //create a new attempt if the user does not already have one
    if($DB->record_exists(constants::M_ATTEMPTSTABLE,['modid'=>$attempt->modid,'userid'=>$user->id])){
        return false;
    }
    $newatt->id = null;
    $newatt->timecreated = time();
    $newatt->userid=$user->id;
    $attemptid = $DB->insert_record(constants::M_ATTEMPTSTABLE,$newatt);
    if(!$attemptid){return false;}

    //get a set of random set of learned terms, and create an assoc for each, if the user does not already have one
    $learnedTermCount = round(rand(50,100) * 0.01 * count($terms));
    if($learnedTermCount>count($assocs)){$learnedTermCount=count($assocs);}
    $someterms = getRandomSubset($terms, $learnedTermCount );
    foreach($someterms as $term){

        //create a new assoc for the term if the user does not already have one
        if(!$DB->record_exists(constants::M_ASSOCTABLE,['termid'=>$term->id,'userid'=>$user->id])){

            $newassoc = array_pop($assocs);
            if(empty($newassoc)){continue;}
            unset($newassoc->id);
            $newassoc->termid = $term->id;
            $newassoc->userid = $user->id;
            $newassoc->timecreated=time();
            $associd = $DB->insert_record(constants::M_ASSOCTABLE,$newassoc);

            //create a seen entry if there is not one
            if($associd && !$DB->record_exists(constants::M_SEENTABLE,['termid'=>$term->id,'userid'=>$user->id])){
                $seen = new stdClass();
                $seen->termid=$term->id;
                $seen->userid=$user->id;
                $seen->timecreated = time();
                $seenid = $DB->insert_record(constants::M_SEENTABLE,$seen);
            }
        }

    }

    //return true
    return true;
}