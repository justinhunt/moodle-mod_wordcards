<?php
/**
 * Displays the definitions.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'flashcards');
require_login($course, true, $cm);
mod_flashcards_helper::require_view($PAGE->context);

$pagetitle = get_string('definitions', 'mod_flashcards');

$PAGE->set_url('/mod/flashcards/view.php', ['id' => $cmid]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

$tabs = mod_flashcards_helper::get_tabs($PAGE->context, 'definitions');
echo $OUTPUT->render($tabs);

$renderer = $PAGE->get_renderer('mod_flashcards');
echo $renderer->definitions_page();

echo $OUTPUT->footer();
