<?php
/**
 * Module form.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_wordcards\utils;
use mod_wordcards\constants;
/**
 * Module form class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;
        $config = get_config('mod_wordcards');

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name', 'mod_wordcards'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('introduction', 'mod_wordcards'));

        $options = utils::get_lang_options();
        $mform->addElement('select', 'ttslanguage', get_string('ttslanguage', 'mod_wordcards'),
                $options);
        $mform->setDefault('ttslanguage',$config->ttslanguage);

        $mform->addElement('header', 'hdrappearance', get_string('appearance'));
        $mform->setExpanded('hdrappearance');

        //options for practicetype and term count
        $ptype_options_learn = utils::get_practicetype_options(\mod_wordcards_module::WORDPOOL_LEARN);
        $ptype_options_all = utils::get_practicetype_options();
        $termcount_options = [4 => 4, 5 => 5, 6 => 6, 7 => 7,8 => 8,9 => 9,10 => 10,11 => 11,12 => 12,13 => 13,14 => 14,15 => 15];

        $mform->addElement('select', 'step1practicetype', get_string('step1practicetype', 'mod_wordcards'),
                $ptype_options_learn, mod_wordcards_module::PRACTICETYPE_MATCHSELECT);
        $mform->addElement('select', 'step1termcount', get_string('step1termcount', 'mod_wordcards'), $termcount_options, 4);

        $mform->addElement('select', 'step2practicetype', get_string('step2practicetype', 'mod_wordcards'),
                $ptype_options_all,mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step2termcount', get_string('step2termcount', 'mod_wordcards'), $termcount_options, 4);
        $mform->disabledIf('step2termcount', 'step2practicetype', 'eq',mod_wordcards_module::PRACTICETYPE_NONE);

        $mform->addElement('select', 'step3practicetype', get_string('step3practicetype', 'mod_wordcards'),
                $ptype_options_all,mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step3termcount', get_string('step3termcount', 'mod_wordcards'), $termcount_options, 4);
        $mform->disabledIf('step3termcount', 'step3practicetype', 'eq',mod_wordcards_module::PRACTICETYPE_NONE);

        $mform->addElement('select', 'step4practicetype', get_string('step4practicetype', 'mod_wordcards'),
                $ptype_options_all,mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step4termcount', get_string('step4termcount', 'mod_wordcards'), $termcount_options, 4);
        $mform->disabledIf('step4termcount', 'step4practicetype', 'eq',mod_wordcards_module::PRACTICETYPE_NONE);

        $mform->addElement('select', 'step5practicetype', get_string('step5practicetype', 'mod_wordcards'),
                $ptype_options_all,mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step5termcount', get_string('step5termcount', 'mod_wordcards'), $termcount_options, 4);
        $mform->disabledIf('step5termcount', 'step5practicetype', 'eq',mod_wordcards_module::PRACTICETYPE_NONE);


        $mform->addElement('hidden', 'skipreview',0);
        $mform->setType('skipreview',PARAM_INT);
       // $mform->addElement('checkbox', 'skipreview', get_string('skipreview', 'mod_wordcards'));
       // $mform->setDefault('skipreview', 1);
       // $mform->addHelpButton('skipreview', 'skipreview', 'mod_wordcards');

        $mform->addElement('editor', 'finishedstepmsg_editor', get_string('finishedstepmsg', 'mod_wordcards'));
        $mform->setDefault('finishedstepmsg_editor', array('text' => get_string('finishscatterin', 'mod_wordcards')));
        $mform->addHelpButton('finishedstepmsg_editor', 'finishedstepmsg', 'mod_wordcards');

        $mform->addElement('editor', 'completedmsg_editor', get_string('completedmsg', 'mod_wordcards'));
        $mform->setDefault('completedmsg_editor', array('text' => get_string('congratsitsover', 'mod_wordcards')));
        $mform->addHelpButton('completedmsg_editor', 'completedmsg', 'mod_wordcards');


        // Grade.
        $this->standard_grading_coursemodule_elements();

        //grade options
        //for now we hard code this to latest attempt
        $mform->addElement('hidden', 'gradeoptions',constants::M_GRADELATEST);
        $mform->setType('gradeoptions', PARAM_INT);


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
            $data->finishedstepmsg = $data->finishedstepmsg_editor['text'];
            $data->completedmsg = $data->completedmsg_editor['text'];
        }

        return $data;
    }

     public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            $data['finishedstepmsg_editor']['text'] = $data['finishedstepmsg'];
            $data['completedmsg_editor']['text'] = $data['completedmsg'];
        }
    }

}
