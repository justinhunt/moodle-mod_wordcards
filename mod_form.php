<?php
/**
 * Module form.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module form class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name', 'mod_flashcards'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('header', 'hdrappearance', get_string('appearance'));
        $mform->setExpanded('hdrappearance');

        $options = [4 => 4, 5 => 5, 6 => 6];
        $mform->addElement('select', 'localtermcount', get_string('localtermcount', 'mod_flashcards'), $options, 4);
        $mform->addElement('select', 'globaltermcount', get_string('globaltermcount', 'mod_flashcards'), $options, 4);

        $mform->addElement('checkbox', 'skipglobal', get_string('skipglobal', 'mod_flashcards'));
        $mform->setDefault('skipglobal', 1);
        $mform->addHelpButton('skipglobal', 'skipglobal', 'mod_flashcards');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $mform =& $this->_form;
        $mform->addElement('advcheckbox', 'completionwhenfinish', '', get_string('completionwhenfinish', 'mod_flashcards'));
        return array('completionwhenfinish');
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionwhenfinish']);
    }

}
