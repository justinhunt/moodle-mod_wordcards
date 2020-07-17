<?php
/**
 * Displays the set-up phase.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - ishinekk.co.jp
 */

use \mod_wordcards\constants;

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);
$leftover_rows = optional_param('leftover_rows', '', PARAM_TEXT);
$action = optional_param('action', null, PARAM_ALPHA);

$mod = mod_wordcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();

require_login($course, true, $cm);
$mod->require_manage();

$modid = $mod->get_id();
$pagetitle = get_string('import', 'mod_wordcards');
$baseurl = new moodle_url('/mod/wordcards/import.php', ['id' => $cmid]);
$formurl = new moodle_url($baseurl);
$term = null;

$PAGE->set_url($baseurl);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

$output = $PAGE->get_renderer('mod_wordcards');

$form = new mod_wordcards_form_import($formurl->out(false),['leftover_rows'=>$leftover_rows]);

if ($data = $form->get_data()) {
    if (!empty($data->importdata)) {
    	
    	//get delimiter
    	switch($data->delimiter){
    		case 'delim_comma': $delimiter = ',';break;    		
    		case 'delim_pipe': $delimiter = '|';break;
    		case 'delim_tab':
    		default: 
    			$delimiter ="\t";
    	}

    	//get array of rows
    	$rawdata =trim($data->importdata);
    	$rows = explode(PHP_EOL, $rawdata);
    	
    	//prepare results fields
    	$imported = 0;
    	$failed = array();
    	
    	foreach($rows as $row){
    		$cols = explode($delimiter,$row,2);
    		if(count($cols)==2 && !empty($cols[0]) && !empty($cols[1])){ 
				$insertdata = new stdClass();
				$insertdata->modid = $modid;
				$insertdata->term = $cols[0];
				$insertdata->definition = $cols[1];
				$DB->insert_record('wordcards_terms', $insertdata);
				$imported++;
        	}else{
        		$failed[]=$row;
        	}//end of if cols ok 
        }//end of for each
        // Uncomment when migrating to 3.1.
        // redirect($PAGE->url, get_string('termadded', 'mod_wordcards', $data->term));
        $result=new stdClass();
        $result->imported=$imported;
        $result->failed=count($failed);
        $message=get_string('importresults','mod_wordcards',$result);
        
        if(count($failed)>0){
        	$leftover_rows = implode(PHP_EOL,$failed);
        	$formurl->param('leftover_rows',$leftover_rows);
        }
        
        redirect($formurl,$message);
    }
}

echo $output->header();
echo $output->heading($pagetitle);
echo $output->navigation($mod, 'import');
echo $output->box(get_string('importinstructions',constants::M_COMPONENT), 'generalbox', 'intro');

$form->display();
/*
$table = new mod_wordcards_table_terms('tblterms', $mod);
$table->define_baseurl($PAGE->url);
$table->out(25, false);
*/
echo $output->footer();
