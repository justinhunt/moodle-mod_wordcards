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
 * Push settings page for Wordcards
 *
 *
 * @package    mod_wordcards
 * @copyright  2024 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

use mod_wordcards\constants;
use mod_wordcards\utils;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // wordcards instance ID
$action = optional_param('action', constants::M_PUSH_NONE, PARAM_INT);



if ($id) {
    $cm         = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

$PAGE->set_url(constants::M_URL . '/push.php', ['id' => $cm->id]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/wordcards:manage', $modulecontext);
require_capability('mod/wordcards:push', $modulecontext);

// Get an admin settings.
$config = get_config(constants::M_COMPONENT);

// Fetch the likely number of affected records.
$cloneconditions = ['masterinstance' => 0];
$masterconditions = ['masterinstance' => $moduleinstance->masterinstance];
switch($moduleinstance->masterinstance ){
    case constants::M_PUSHMODE_MODULENAME:
        $cloneconditions['name'] = $moduleinstance->name;
        $masterconditions['name'] = $moduleinstance->name;
        $scopedescription = get_string('pushpage_scopemodule', constants::M_COMPONENT, $moduleinstance->name);
        break;
    case constants::M_PUSHMODE_COURSE:
        $cloneconditions['course'] = $moduleinstance->course;
        $masterconditions['course'] = $moduleinstance->course;
        $scopedescription = get_string('pushpage_scopecourse', constants::M_COMPONENT, $course->fullname);
        break;
    case constants::M_PUSHMODE_SITE:
        $scopedescription = get_string('pushpage_scopesite', constants::M_COMPONENT);
        break;
    default:
        // We should never get here, nor should we push anything if we do.
        $cloneconditions['id'] = 0;
        $masterconditions['id'] = 0;
        $scopedescription = get_string('pushpage_scopenone', constants::M_COMPONENT);
        break;
}
$clonecount = $DB->count_records(constants::M_TABLE, $cloneconditions);
$mastercount = $DB->count_records(constants::M_TABLE, $masterconditions);

switch($action){

    case constants::M_PUSH_TRANSCRIBER:
        $updatefields = ['transcriber'];
        break;
    case constants::M_PUSH_SHOWLANGCHOOSER:
        $updatefields = ['showlangchooser'];
        break;
    case constants::M_PUSH_LEARNPOINT:
        $updatefields = ['learnpoint'];
        break;
    case constants::M_PUSH_MAXATTEMPTS:
        $updatefields = ['maxattempts'];
        break;
    case constants::M_PUSH_STEPSMODEOPTIONS:
        $updatefields = ['step1practicetype', 'step1termcount',
        'step2practicetype', 'step2termcount',
        'step3practicetype', 'step3termcount',
        'step4practicetype', 'step4termcount',
        'step5practicetype', 'step5termcount'];
        break;
    case constants::M_PUSH_FREEMODEOPTIONS:
        $updatefields = ['freemodeoptions'];
        break;
    case constants::M_PUSH_JOURNEYMODE:
        $updatefields = ['journeymode'];
        break;
    case constants::M_PUSH_VIDEOEXAMPLES:
        $updatefields = ['videoexamples'];
        break;
    case constants::M_PUSH_SHOWIMAGEFLIP:
        $updatefields = ['showimageflip'];
        break;
    case constants::M_PUSH_FRONTFACEFLIP:
        $updatefields = ['frontfaceflip'];
        break;
    case constants::M_PUSH_LCOPTIONS:
        $updatefields = ['lcoptions'];
        break;
    case constants::M_PUSH_MSOPTIONS:
        $updatefields = ['msoptions'];
        break;
    case constants::M_PUSH_SGOPTIONS:
        $updatefields = ['sgoptions'];
        break;
    case constants::M_PUSH_NONE:
    default:
        $updatefields = [];
}

// Do the DB updates and then refresh.
if ($updatefields && count($updatefields) > 0) {
    foreach ($updatefields as $thefield) {
        $DB->set_field(constants::M_TABLE, $thefield, $moduleinstance->{$thefield}, $cloneconditions);
    }
    redirect($PAGE->url, get_string('pushpage_done', constants::M_COMPONENT,$clonecount), 10);
}

// Set up the page header.
$pagetitle = get_string('pushpage', constants::M_COMPONENT);
$PAGE->set_title(format_string($moduleinstance->name. ' ' . $pagetitle ));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('incourse');
$mode = "push";

// This puts all our display logic into the renderer.php files in this plugin.
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
echo $renderer->header();
echo $renderer->heading($pagetitle);

echo html_writer::div(get_string('pushpage_explanation', constants::M_COMPONENT), constants::M_COMPONENT . '_pushpageexplanation');
echo html_writer::div($scopedescription, constants::M_COMPONENT . '_pushpageexplanation');


if ($moduleinstance->masterinstance && $clonecount > 0) {
    echo html_writer::div(get_string('pushpage_clonecount', constants::M_COMPONENT, $clonecount), constants::M_COMPONENT . '_clonecount');
    echo html_writer::div(get_string('pushpage_mastercount', constants::M_COMPONENT, $mastercount), constants::M_COMPONENT . '_mastercount');
    echo $renderer->push_buttons_menu($cm);
} else if ($moduleinstance->masterinstance && $clonecount == 0) {
    echo get_string('pushpage_noclones', constants::M_COMPONENT);
} else {
    echo get_string('notmasterinstance', constants::M_COMPONENT);
}

echo $renderer->footer();
return;
