<?php
/**
 * Displays the set-up phase.
 *
 * @package mod_flashcards
 * @author  Justin Hunt - ishinekk.co.jp
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);
$leftover_rows = optional_param('leftover_rows', '', PARAM_TEXT);
$action = optional_param('action', null, PARAM_ALPHA);

$mod = mod_flashcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();

require_login($course, true, $cm);
$mod->require_manage();

$modid = $mod->get_id();
$pagetitle = get_string('import', 'mod_flashcards');
$baseurl = new moodle_url('/mod/flashcards/import.php', ['id' => $cmid]);
$formurl = new moodle_url($baseurl);
$term = null;

$PAGE->set_url($baseurl);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

$form = new mod_flashcards_form_import($formurl->out(false),['leftover_rows'=>$leftover_rows]);

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
				$DB->insert_record('flashcards_terms', $insertdata);
				$imported++;
        	}else{
        		$failed[]=$row;
        	}//end of if cols ok 
        }//end of for each
        // Uncomment when migrating to 3.1.
        // redirect($PAGE->url, get_string('termadded', 'mod_flashcards', $data->term));
        $result=new stdClass();
        $result->imported=$imported;
        $result->failed=count($failed);
        $message=get_string('importresults','mod_flashcards',$result);
        
        if(count($failed)>0){
        	$leftover_rows = implode(PHP_EOL,$failed);
        	$formurl->param('leftover_rows',$leftover_rows);
        }
        
        redirect($formurl,$message);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

$tabs = mod_flashcards_helper::get_tabs($mod, 'import');
echo $OUTPUT->render($tabs);

$form->display();
/*
$table = new mod_flashcards_table_terms('tblterms', $mod);
$table->define_baseurl($PAGE->url);
$table->out(25, false);
*/
echo $OUTPUT->footer();
