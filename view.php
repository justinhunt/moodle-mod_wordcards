<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Displays the definitions.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_wordcards\constants;
use mod_wordcards\utils;

$cmid = required_param('id', PARAM_INT);

$mod = mod_wordcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$currentstate = mod_wordcards_module::STATE_TERMS;

require_login($course, true, $cm);

// if free mode then lets do that
switch ($mod->get_mod()->journeymode ) {
    case constants::MODE_FREE:
        redirect($CFG->wwwroot . '/mod/wordcards/freemode.php?id=' . $cmid);
    case constants::MODE_SESSION:
        redirect($CFG->wwwroot . '/mod/wordcards/sessionmode.php?id=' . $cmid);
    case constants::MODE_STEPS:
    case constants::MODE_STEPSTHENFREE:
    case constants::MODE_SESSIONTHENFREE:
    default:
        // Just carry on.
}


$mod->require_view();
$mod->resume_progress($currentstate);
$moduleinstance = $mod->get_mod();

// trigger module viewed event
$mod->register_module_viewed();

// log usage
utils::stage_remote_process_job($mod->get_mod()->ttslanguage, $cmid);

// $pagetitle = get_string('tabdefinitions', 'mod_wordcards');
$pagetitle = format_string($mod->get_mod()->name, true, $course->id);

$PAGE->set_url('/mod/wordcards/view.php', ['id' => $cmid]);
// $PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, $course->id));
$PAGE->set_title($pagetitle);
$PAGE->force_settings_menu(true);
$modulecontext = $mod->get_context();
// Get an admin settings
$config = get_config(constants::M_COMPONENT);
if ($config->enablesetuptab) {
    $PAGE->set_pagelayout('popup');
} else {
    $PAGE->set_pagelayout('incourse');
}


$renderer = $PAGE->get_renderer('mod_wordcards');
$PAGE->requires->js_call_amd(constants::M_COMPONENT . "/mywords", 'init', []);
// $PAGE->requires->js_call_amd(constants::M_COMPONENT . "/cardactions", 'init', []);
$PAGE->requires->css(new moodle_url('/mod/wordcards/freemode.css'));

// prepare definitions data (which also outputs AMD )
$definitions = $mod->get_terms();
if (empty($definitions)) {
    $definitionsdata = [];
} else {
    $definitionsdata = $renderer->definitions_page_data($mod, $definitions);
}

// begin HTML output
echo $renderer->header();
echo $renderer->heading($pagetitle, 3, 'main');

// show open close dates and module intro
$hasopenclosedates = $moduleinstance->viewend > 0 || $moduleinstance->viewstart > 0;
if ($hasopenclosedates) {
    echo $renderer->box($renderer->show_open_close_dates($moduleinstance), 'generalbox');
}


// enforce open close dates
if ($hasopenclosedates) {
    $currenttime = time();
    $closed = false;
    if ( $currenttime > $moduleinstance->viewend && $moduleinstance->viewend > 0) {
        echo get_string('activityisclosed', constants::M_COMPONENT);
        $closed = true;
    } else if ($currenttime < $moduleinstance->viewstart) {
        echo get_string('activityisnotopenyet', constants::M_COMPONENT);
        $closed = true;
    }
    // If we are not a teacher and the activity is closed/not-open leave at this point.
    if (!has_capability('mod/wordcards:preview', $modulecontext) && $closed) {
        echo $renderer->footer();
        exit;
    }
}

// show activity description - pre m4.0
if ( $CFG->version < 2022041900) {
    if (!empty($mod->get_mod()->intro)) {
        $moduleintro = format_module_intro('wordcards', $mod->get_mod(), $cm->id);
        echo $renderer->box($moduleintro, 'generalbox', 'intro');
    }
}

echo $renderer->navigation($mod, $currentstate);

// do definitions
if (empty($definitions)) {
    echo $renderer->no_definitions_yet($mod);
} else {
    $definitionsdata['isstepsmode'] = 1;
    echo $renderer->render_from_template('mod_wordcards/definitions_page', $definitionsdata);
}
echo $renderer->footer();
