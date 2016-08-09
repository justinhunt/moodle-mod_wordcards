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

        $this->standard_intro_elements(get_string('introduction', 'mod_flashcards'));

        $mform->addElement('header', 'hdrappearance', get_string('appearance'));
        $mform->setExpanded('hdrappearance');

        $options = [4 => 4, 5 => 5, 6 => 6];
        $mform->addElement('select', 'localtermcount', get_string('localtermcount', 'mod_flashcards'), $options, 4);
        $mform->addElement('select', 'globaltermcount', get_string('globaltermcount', 'mod_flashcards'), $options, 4);

        $mform->addElement('checkbox', 'skipglobal', get_string('skipglobal', 'mod_flashcards'));
        $mform->setDefault('skipglobal', 1);
        $mform->addHelpButton('skipglobal', 'skipglobal', 'mod_flashcards');

        $mform->addElement('editor', 'finishedscattermsg_editor', get_string('finishedscattermsg', 'mod_flashcards'));
        $mform->setDefault('finishedscattermsg_editor', array('text' => get_string('finishscatterin', 'mod_flashcards')));
        $mform->addHelpButton('finishedscattermsg_editor', 'finishedscattermsg', 'mod_flashcards');

        $mform->addElement('editor', 'completedmsg_editor', get_string('completedmsg', 'mod_flashcards'));
        $mform->setDefault('completedmsg_editor', array('text' => get_string('congratsitsover', 'mod_flashcards')));
        $mform->addHelpButton('completedmsg_editor', 'completedmsg', 'mod_flashcards');

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

    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data->finishedscattermsg = $data->finishedscattermsg_editor['text'];
            $data->completedmsg = $data->completedmsg_editor['text'];
        }

        return $data;
    }

     public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            $data['finishedscattermsg_editor']['text'] = $data['finishedscattermsg'];
            $data['completedmsg_editor']['text'] = $data['completedmsg'];
        }
    }

}
