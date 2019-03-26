<?php
/**
 * Module form.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module form class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name', 'mod_wordcards'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('introduction', 'mod_wordcards'));

        $mform->addElement('header', 'hdrappearance', get_string('appearance'));
        $mform->setExpanded('hdrappearance');

        $options = [4 => 4, 5 => 5, 6 => 6];
        $mform->addElement('select', 'localtermcount', get_string('localtermcount', 'mod_wordcards'), $options, 4);
        $mform->addElement('select', 'globaltermcount', get_string('globaltermcount', 'mod_wordcards'), $options, 4);

        $mform->addElement('checkbox', 'skipglobal', get_string('skipglobal', 'mod_wordcards'));
        $mform->setDefault('skipglobal', 1);
        $mform->addHelpButton('skipglobal', 'skipglobal', 'mod_wordcards');

        $mform->addElement('editor', 'finishedscattermsg_editor', get_string('finishedscattermsg', 'mod_wordcards'));
        $mform->setDefault('finishedscattermsg_editor', array('text' => get_string('finishscatterin', 'mod_wordcards')));
        $mform->addHelpButton('finishedscattermsg_editor', 'finishedscattermsg', 'mod_wordcards');

        $mform->addElement('editor', 'completedmsg_editor', get_string('completedmsg', 'mod_wordcards'));
        $mform->setDefault('completedmsg_editor', array('text' => get_string('congratsitsover', 'mod_wordcards')));
        $mform->addHelpButton('completedmsg_editor', 'completedmsg', 'mod_wordcards');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $mform =& $this->_form;
        $mform->addElement('advcheckbox', 'completionwhenfinish', '', get_string('completionwhenfinish', 'mod_wordcards'));
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
