<?php
/**
 * Helper.
 *
 * @package mod_flashcards
 * @author  Justin Hunt - ishinekk.co.jp
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Helper class.
 *
 * @package mod_flashcards
 * @author  Justin Hunt - ishinekk.co.jp
 */
class mod_flashcards_form_import extends moodleform {

   public function definition() {
        $mform = $this->_form;
        $leftover_rows = $this->_customdata['leftover_rows'];
        
        $delimiter_options=array('delim_tab'=>get_string('delim_tab','mod_flashcards'),
         	'delim_comma'=>get_string('delim_comma','mod_flashcards'),
         	'delim_pipe'=>get_string('delim_pipe','mod_flashcards')
         );
        $mform->addElement('select', 'delimiter', get_string('delimiter', 'mod_flashcards'),$delimiter_options);
        $mform->setType('delimiter', PARAM_NOTAGS);
        $mform->setDefault('delimiter', 'delim_tab');
        $mform->addRule('delimiter', null, 'required', null, 'client');

        
        $mform->addElement('textarea', 'importdata', get_string('importdata', 'mod_flashcards'));
        $mform->setDefault('importdata', $leftover_rows);
        $mform->setType('importdata', PARAM_NOTAGS);
        $mform->addRule('importdata', null, 'required', null, 'client');
        $this->add_action_buttons(false);
    }

}
