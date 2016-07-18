<?php
/**
 * Displays the set-up phase.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

require_once(__DIR__ . '/../../config.php');

$cmid = required_param('id', PARAM_INT);
$termid = optional_param('termid', null, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);

$mod = mod_flashcards_module::get_by_cmid($cmid);
$course = $mod->get_course();
$cm = $mod->get_cm();

require_login($course, true, $cm);
$mod->require_manage();

$modid = $mod->get_id();
$pagetitle = get_string('setup', 'mod_flashcards');
$baseurl = new moodle_url('/mod/flashcards/setup.php', ['id' => $cmid]);
$formurl = new moodle_url($baseurl);
$term = null;

$PAGE->set_url($baseurl);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);

if ($action == 'delete') {
    confirm_sesskey();
    $mod->delete_term($termid);
    redirect($PAGE->url, get_string('termdeleted', 'mod_flashcards'));

} else if ($action == 'edit') {
    // Adding those parameters ensures that we confirm that the term belongs to the right module after submission.
    $formurl->param('action', 'edit');
    $formurl->param('termid', 'termid');
    $term = $DB->get_record('flashcards_terms', ['modid' => $modid, 'id' => $termid], '*', MUST_EXIST);
}

$form = new mod_flashcards_form_term($formurl->out(false), ['termid' => $term ? $term->id : 0]);
if ($term) {
    $form->set_data($term);
}

if ($data = $form->get_data()) {
    if (empty($data->termid)) {
        $data->modid = $modid;
        $DB->insert_record('flashcards_terms', $data);
        redirect($PAGE->url, get_string('termadded', 'mod_flashcards', $data->term));

    } else {
        $data->id = $data->termid;
        $DB->update_record('flashcards_terms', $data);
        redirect($PAGE->url, get_string('termsaved', 'mod_flashcards', $data->term));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($pagetitle);

$tabs = mod_flashcards_helper::get_tabs($mod, 'setup');
echo $OUTPUT->render($tabs);

$form->display();

$table = new mod_flashcards_table_terms('tblterms', $mod);
$table->define_baseurl($PAGE->url);
$table->out(25, false);

echo $OUTPUT->footer();
