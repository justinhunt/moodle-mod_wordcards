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

$PAGE->set_url('/mod/wordcards/transcriber.php', ['id' => $cmid]);
$PAGE->navbar->add($pagetitle, $PAGE->url);
$PAGE->set_heading(format_string($course->fullname, true, [context_course::instance($course->id)]));
$PAGE->set_title($pagetitle);


$output = $PAGE->get_renderer('mod_wordcards');

echo $output->header();
echo $output->heading($pagetitle);



$renderer = $PAGE->get_renderer('mod_wordcards');

?>
    <div class="row">
        <div class="col">
            <textarea id="thetranscript"></textarea>
        </div>
    </div>

<div class="row">
    <div class="col">
        <button id="start-button" class="button-xl" title="Start Transcription" type="button">
            <i class="fa fa-microphone"></i> Start
        </button>
        <button id="stop-button" class="button-xl" title="Stop Transcription" type="button"><i
                    class="fa fa-stop-circle"></i> Stop
        </button>
        <button id="reset-button" class="button-xl button-secondary" title="Clear Transcript">
            Clear Transcript
        </button>
    </div>
    <div class="col">
        <a class="float-right" href="https://aws.amazon.com/free/" aria-label="Amazon Web Services">
            <img id="logo" src="AWS_logo_RGB.png" alt="AWS Logo" />
        </a>
    </div>
</div>

<?php
$opts = [];
$opts['language']='en-AU';
$opts['region']='us-east-1';
$opts['accessid']='YYYYY';
$opts['secretkey']='XXXXXXXXX';
$PAGE->requires->js_call_amd("mod_wordcards/transcribehelper", 'init', array($opts));

echo $output->footer();
