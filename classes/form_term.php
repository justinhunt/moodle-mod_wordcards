<?php
/**
 * Term form.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Term form class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_form_term extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $termid = $this->_customdata['termid'];

        $mform->addElement('hidden', 'termid');
        $mform->setType('termid', PARAM_INT);
        $mform->setConstant('termid', $termid);

        $mform->addElement('text', 'term', get_string('term', 'mod_flashcards'));
        $mform->setType('term', PARAM_NOTAGS);
        $mform->addRule('term', null, 'required', null, 'client');
        $mform->addRule('term', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'definition', get_string('definition', 'mod_flashcards'));
        $mform->setType('definition', PARAM_NOTAGS);
        $mform->addRule('definition', null, 'required', null, 'client');

        $this->add_action_buttons(false);
    }

}
