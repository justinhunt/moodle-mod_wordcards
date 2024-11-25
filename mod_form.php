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

    public function __construct($current, $section, $cm, $course, $ajaxformdata=null,$customdata=null) {
        global $CFG;
        $this->current   = $current;
        $this->_instance = $current->instance;
        $this->_section  = $section;
        $this->_cm       = $cm;
        $this->_course   = $course;
        if ($this->_cm) {
            $this->context = context_module::instance($this->_cm->id);
        } else {
            $this->context = context_course::instance($course->id);
        }
        // Set the course format.
        require_once($CFG->dirroot . '/course/format/lib.php');
        $this->courseformat = course_get_format($course);
        // Guess module name if not set.
        if (is_null($this->_modname)) {
            $matches = array();
            if (!preg_match('/^mod_([^_]+)_mod_form$/', get_class($this), $matches)) {
                debugging('Rename form to mod_xx_mod_form, where xx is name of your module');
                print_error('unknownmodulename');
            }
            $this->_modname = $matches[1];
        }
        $this->init_features();
        $action = 'modedit.php';
        moodleform::__construct($action, $customdata, 'post', '', null, true, $ajaxformdata);
    }

    public function definition() {
        $mform = $this->_form;

        //Add this activity specific form fields
        //We want to do this procedurally because in setup tabs we want to show a subset of this form
        // with just the activity specific fields,and we use a custom form and the same elements
        utils::add_mform_elements($mform, $this->context);

        // Grade.
        $this->standard_grading_coursemodule_elements();

        //grade options
        //for now we hard code this to latest attempt
        $gradeoptions = utils::get_grade_options();
        $mform->addElement('select', 'gradeoptions', get_string('gradeoptions', constants::M_COMPONENT), $gradeoptions);
        $mform->setDefault('gradeoptions', constants::M_GRADELATEST);
        $mform->addHelpButton('gradeoptions', 'gradeoptions', constants::M_COMPONENT);
        $mform->addElement('static', 'gradeoptions_details', '',
            get_string('gradeoptions_details', constants::M_COMPONENT));

        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        // add standard buttons, common to all modules
        $this->add_action_buttons();

  }

    public function add_completion_rules() {
        global $CFG;

        $mform =& $this->_form;
        $completionfields=['completionwhenfinish','completionwhenlearned'];
        $suffixedfields=[];

        foreach($completionfields as $field){
            $suffixedname = $this->get_suffixed_name($field);
            $mform->addElement('advcheckbox', $suffixedname, '', get_string($field, 'mod_wordcards'));
            $suffixedfields[] = $suffixedname;
        }
        return $suffixedfields;
    }

    public function completion_rule_enabled($data) {
        global $CFG;
        $completionfields=['completionwhenfinish','completionwhenlearned'];
        foreach($completionfields as $field){
            if(!empty($data[$this->get_suffixed_name($field)])){return true;}
        }
        return false;
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            //$data->finishedstepmsg = $data->finishedstepmsg_editor['text'];
            //$data->completedmsg = $data->completedmsg_editor['text'];
        }
        return $data;
    }

     public function data_preprocessing(&$data) {
        if ($this->current->instance) {
            $data = utils::unpack_freemode_options($data);
            //$data =  utils::prepare_file_and_json_stuff($data,$this->context);
        }
    }
    
	public function validation($data, $files) {
        $errors = parent::validation($data, $files);
          if (!empty($data['viewend'])) {
            if ($data['viewend'] < $data['viewstart']) {
                $errors['viewend'] = "End date should be after Start Date";
            }
        }
        return $errors;
    }

    private function get_suffixed_name($completionfieldname){
        global $CFG;
        $m43 = $CFG->version >= 2023100900;
        $suffix = $m43 ? $this->get_suffix() : '';
        return $suffix . $completionfieldname;
    }
}
