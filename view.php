<?php
/**
 * Displays the definitions.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);

$mod = mod_wordcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$currentstate = mod_wordcards_module::STATE_TERMS;

require_login($course, true, $cm);
$mod->require_view();
$mod->resume_progress($currentstate);

$pagetitle = get_string('tabdefinitions', 'mod_wordcards');

$PAGE->set_url('/mod/wordcards/view.php', ['id' => $cmid]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

$output = $PAGE->get_renderer('mod_wordcards');

echo $output->header();
echo $output->heading($pagetitle);

if (!empty($mod->get_mod()->intro)) {
    echo $output->box(format_module_intro('wordcards', $mod->get_mod(), $cm->id), 'generalbox', 'intro');
}

echo $output->navigation($mod, $currentstate);

$renderer = $PAGE->get_renderer('mod_wordcards');
echo $renderer->definitions_page($mod);

echo $output->footer();
