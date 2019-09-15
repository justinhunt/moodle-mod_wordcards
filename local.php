<?php
/**
 * Displays the local scatter.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);

$mod = mod_wordcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();
$currentstate = mod_wordcards_module::STATE_LOCAL;

require_login($course, true, $cm);
$mod->require_view();
$mod->resume_progress($currentstate);

$pagetitle = get_string('localscatter', 'mod_wordcards');

$PAGE->set_url('/mod/wordcards/local.php', ['id' => $cmid]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/glidejs@2.1.0/dist/css/glide.core.min.css'));

$renderer = $PAGE->get_renderer('mod_wordcards');

echo $renderer->header();
echo $renderer->heading($pagetitle);

if (!empty($mod->get_mod()->intro)) {
    echo $renderer->box(format_module_intro('wordcards', $mod->get_mod(), $cm->id), 'generalbox', 'intro');
}

echo $renderer->navigation($mod, $currentstate);


$localpracticetype = $mod->get_localpracticetype();
switch ($localpracticetype){

    case mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
    case mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
    case mod_wordcards_module::PRACTICETYPE_DICTATION:
        echo $renderer->local_a4e_page($mod);
        break;

    case mod_wordcards_module::PRACTICETYPE_SPEECHCARDS:
        echo $renderer->local_speechcards($mod);
        break;

    case mod_wordcards_module::PRACTICETYPE_SCATTER:
    default:
        echo $renderer->local_page($mod);
}


echo $renderer->footer();
