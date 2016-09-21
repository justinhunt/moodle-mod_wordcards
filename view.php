<?php
/**
 * Displays the definitions.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);

$mod = mod_flashcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$currentstate = mod_flashcards_module::STATE_TERMS;

require_login($course, true, $cm);
$mod->require_view();
$mod->resume_progress($currentstate);

$pagetitle = get_string('tabdefinitions', 'mod_flashcards');

$PAGE->set_url('/mod/flashcards/view.php', ['id' => $cmid]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

if (!empty($mod->get_mod()->intro)) {
    echo $OUTPUT->box(format_module_intro('flashcards', $mod->get_mod(), $cm->id), 'generalbox', 'intro');
}

$tabs = mod_flashcards_helper::get_tabs($mod, $currentstate);
echo $OUTPUT->render($tabs);

$renderer = $PAGE->get_renderer('mod_flashcards');
echo $renderer->definitions_page($mod);

echo $OUTPUT->footer();
