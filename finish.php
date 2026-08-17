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
 * Page to record the 'end' state.
 *
 * @package    mod_wordcards
 * @author     Frédéric Massart - FMCorz.net
 * @copyright  2016 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use mod_wordcards\utils;
use mod_wordcards\constants;

$cmid = required_param('id', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);
$embed = optional_param('embed', 0, PARAM_INT);

$mod = mod_wordcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$currentstate = mod_wordcards_module::STATE_END;

require_login($course, true, $cm);
require_sesskey();
$mod->require_view();
$mod->resume_progress($currentstate);

utils::update_finalgrade($mod->get_id());

$pagetitle = format_string($mod->get_mod()->name, true, $course->id);
$pagetitle .= ': ' . get_string('activitycompleted', 'mod_wordcards');

$PAGE->set_url('/mod/wordcards/finish.php', ['id' => $cmid, 'sesskey' => $sesskey, 'embed' => $embed]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, $course->id));
$PAGE->set_title($pagetitle);

//Get admin settings
$config = get_config(constants::M_COMPONENT);

//get our page layout
if ($mod->get_mod()->foriframe == 1 || $embed == 1) {
    $PAGE->set_pagelayout('embedded');
} else if ($config->enablesetuptab || $embed == 2) {
    $PAGE->set_pagelayout('popup');
    $PAGE->add_body_class('poodll-wordcards-embed');
} else {
    $PAGE->set_pagelayout('incourse');
}

$output = $PAGE->get_renderer('mod_wordcards');

echo $output->header();
echo $output->heading($pagetitle);


$navdisabled = false;
echo $output->navigation($mod, $currentstate, $navdisabled);

$renderer = $PAGE->get_renderer('mod_wordcards');
echo $renderer->finish_page($mod);

echo $output->footer();
