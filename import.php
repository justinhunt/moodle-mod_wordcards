<?php
/**
 * Displays the set-up phase.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - ishinekk.co.jp
 */

use \mod_wordcards\constants;
use \mod_wordcards\utils;

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);
$leftover_rows = optional_param('leftover_rows', '', PARAM_TEXT);
$action = optional_param('action', null, PARAM_ALPHA);

$mod = mod_wordcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$modulecontext = context_module::instance($cm->id);

require_login($course, true, $cm);
$mod->require_manage();

$modid = $mod->get_id();
$pagetitle = format_string($mod->get_mod()->name, true, $mod->get_course());
$pagetitle .= ': ' . get_string('import', 'mod_wordcards');
$baseurl = new moodle_url('/mod/wordcards/import.php', ['id' => $cmid]);
$formurl = new moodle_url($baseurl);
$term = null;

$PAGE->set_url($baseurl);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

//Get admin settings
$config = get_config(constants::M_COMPONENT);
if($config->enablesetuptab){
    $PAGE->set_pagelayout('popup');
}else{
    $PAGE->set_pagelayout('course');
}

$renderer = $PAGE->get_renderer('mod_wordcards');


//prepare the import form and import any data if we are supposed to
$insertdatarows=[];
$importform = new mod_wordcards_form_import($formurl->out(false),['leftover_rows'=>$leftover_rows]);
if ($data = $importform->get_data()) {
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

        //prepare each row for import
    	foreach($rows as $rowdata) {
            $insertdatarows[] = utils::prepare_import_data_row($rowdata, $delimiter, $mod);
        }
    }
}

//if we have glossaries, prepare the glossary import form and import any glossary data if we are supposed to
$glossaries=utils::fetch_glossaries_list($course->id);
$glossariesform=false;
$editdatarows=[];
$delimiter ="|";//"\t";
if($glossaries && count($glossaries)>0) {
    $glossariesform = new mod_wordcards_form_glossaryimport($formurl->out(false), ['glossaries' => $glossaries]);
    if ($data = $glossariesform->get_data()) {
        if (!empty($data->glossary)) {
            $glossaryid = $data->glossary;
            $glossary = $DB->get_record(constants::M_GLOSSARYTABLE, ['id' => $glossaryid, 'course' => $course->id], '*', IGNORE_MISSING);
            if ($glossary) {
                $entries = $DB->get_records(constants::M_GLOSSARYENTRIESTABLE, ['glossaryid' => $glossaryid], 'concept ASC', '*');
                if ($entries) {
                    foreach ($entries as $entry) {
                        if($data->loadthensave){
                            $editdatarows[]= strip_tags($entry->concept) . ' ' . $delimiter . ' '. strip_tags($entry->definition);
                        }else {
                            $insertdatarows[] = utils::prepare_import_data_row($entry->concept . $delimiter . $entry->definition, $delimiter, $mod);
                        }
                    }//end of for each entry

                    //if loadthensave
                    //we will load the rows up into the import form
                    if (!empty($editdatarows)) {
                        $import_rows = implode(PHP_EOL, $editdatarows);
                        $importform->set_data(['importdata' => $import_rows]);
                        //the code below worked well, but it was GET and so big data failed with an error
                      //  $formurl->param('leftover_rows', $leftover_rows);
                      //  $message= get_string('loadedglossaryentries', constants::M_COMPONENT, count($editdatarows));
                      //  redirect($formurl, $message);
                    }

                }//end of if entries
            }//end of if glossary
        }//end of if not empty data glossary
    }//end of if form has data
}//end of if glossaries in the course at all

//if we have importdatarows from glossary or from import form, do it
if(count($insertdatarows)>0) {
    //prepare results fields
    $imported = 0;
    $failed = array();

    //loop through each row and insert it
    foreach ($insertdatarows as $insertdata) {
        if ($insertdata) {
            $DB->insert_record(constants::M_TERMSTABLE, $insertdata);
            $imported++;
        } else {
            $failed[] = $rowdata;
        }
    }//end of for each

    //if successful update our passagehash update flag
    if ($imported > 0) {
        $DB->update_record(constants::M_TABLE, array('id' => $mod->get_mod()->id, 'hashisold' => 1));
    }

    //redirect to the import page with the results
    $result = new stdClass();
    $result->imported = $imported;
    $result->failed = count($failed);
    $message = get_string('importresults', 'mod_wordcards', $result);

    //prepare the leftover rows to be displayed in the form
    if (count($failed) > 0) {
        $leftover_rows = implode(PHP_EOL, $failed);
        $formurl->param('leftover_rows', $leftover_rows);
    }
    redirect($formurl, $message);
}

//display the page ... no form data to process
echo $renderer->header();
echo $renderer->heading($pagetitle);
echo $renderer->navigation($mod, 'import');

//display the import form
echo "<h5>".get_string('importfromtext',constants::M_COMPONENT)."</h5>";
echo $renderer->box(get_string('importinstructions',constants::M_COMPONENT), 'generalbox wordcards_importintro', 'intro');
$importform->display();

//if we have glossaries, display the glossary import form
if($glossariesform){
    echo "<hr />";
    echo "<h5>".get_string('importfromglossary',constants::M_COMPONENT)."</h5>";
    echo $renderer->box(get_string('glossaryimportinstructions',constants::M_COMPONENT), 'generalbox wordcards_importintro', 'intro');
    $glossariesform->display();
}

//display the export form
$exporturls=[];
$exporturls['delim_comma_url']= moodle_url::make_pluginfile_url($modulecontext->id, constants::M_COMPONENT, 'exportcomma', 0, "/", 'export.csv', true);
$exporturls['delim_tab_url']= moodle_url::make_pluginfile_url($modulecontext->id, constants::M_COMPONENT, 'exporttab', 0, "/", 'export.csv', true);
$exporturls['delim_pipe_url']= moodle_url::make_pluginfile_url($modulecontext->id, constants::M_COMPONENT, 'exportpipe', 0, "/", 'export.csv', true);
echo $renderer->render_from_template('mod_wordcards/simpleexportform', $exporturls);
echo $renderer->footer();
