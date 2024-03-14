<?php
/**
 * Helper.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - ishinekk.co.jp
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use \mod_wordcards\constants;
use \mod_wordcards\utils;

/**
 * Helper class.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - ishinekk.co.jp
 */
class mod_wordcards_form_glossaryimport extends moodleform {

   public function definition() {
        $mform = $this->_form;
        //glossaries select
        $glossaries = $this->_customdata['glossaries'];
        $mform->addElement('select', 'glossary', get_string('glossary', constants::M_COMPONENT),$glossaries);
        $mform->setType('glossary', PARAM_NOTAGS);
        $mform->addRule('glossary', null, 'required', null, 'client');
       //load then edit or not
       $mform->addElement('advcheckbox', 'loadthensave', get_string('loadthensave',constants::M_COMPONENT));
       $mform->setDefault('loadthensave', 1);

       $this->add_action_buttons(false, get_string('importfromglossary', constants::M_COMPONENT));
    }

}
