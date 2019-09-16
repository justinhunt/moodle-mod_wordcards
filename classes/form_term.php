<?php
/**
 * Term form.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use \mod_wordcards\utils;
use \mod_wordcards\constants;


/**
 * Term form class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_form_term extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $termid = $this->_customdata['termid'];
        $ttslanguage = $this->_customdata['ttslanguage'];

        $mform->addElement('hidden', 'termid');
        $mform->setType('termid', PARAM_INT);
        $mform->setConstant('termid', $termid);

        $mform->addElement('text', 'term', get_string('term', constants::M_COMPONENT));
        $mform->setType('term', PARAM_NOTAGS);
        $mform->addRule('term', null, 'required', null, 'client');
        $mform->addRule('term', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'definition', get_string('definition', constants::M_COMPONENT));
        $mform->setType('definition', PARAM_NOTAGS);
        $mform->addRule('definition', null, 'required', null, 'client');

        $mform->addElement('textarea', 'alternates', get_string('alternates', constants::M_COMPONENT));
        $mform->setType('alternates', PARAM_NOTAGS);

        $options=utils::get_tts_voices($ttslanguage);
        $mform->addElement('select', 'ttsvoice', get_string('ttsvoice', 'mod_wordcards'),
                $options);

        $filemanageropts = utils::fetch_filemanager_opts('audio');
        $mform->addElement('filemanager', 'audio_filemanager', get_string('audiofile', constants::M_COMPONENT), null,
                $filemanageropts);

        $filemanageropts = utils::fetch_filemanager_opts('image');
        $mform->addElement('filemanager', 'image_filemanager', get_string('imagefile', constants::M_COMPONENT), null,
                $filemanageropts);

        $this->add_action_buttons(false);
    }

}
