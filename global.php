<?php
/**
 * Displays the global scatter.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);

$mod = mod_flashcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$currentstate = mod_flashcards_module::STATE_GLOBAL;

require_login($course, true, $cm);
$mod->require_view();
$mod->resume_progress($currentstate);

$pagetitle = get_string('globalscatter', 'mod_flashcards');

$PAGE->set_url('/mod/flashcards/global.php', ['id' => $cmid]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

$output = $PAGE->get_renderer('mod_flashcards');

echo $output->header();
echo $output->heading($pagetitle);

if (!empty($mod->get_mod()->intro)) {
    echo $output->box(format_module_intro('flashcards', $mod->get_mod(), $cm->id), 'generalbox', 'intro');
}

echo $output->navigation($mod, $currentstate);

$renderer = $PAGE->get_renderer('mod_flashcards');
echo $renderer->global_page($mod);

echo $output->footer();
