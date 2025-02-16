<?php
/**
 * Image Wizard form.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_wordcards\utils;
use mod_wordcards\constants;


/**
 * imagegen form class.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 */
class mod_wordcards_form_imagegen extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $termid = $this->_customdata['termid'];
        $imagemaker = $this->_customdata['imagemaker'];


        $mform->addElement('hidden', 'termid');
        $mform->setType('termid', PARAM_INT);
        $mform->setConstant('termid', $termid);

        $mform->addElement('hidden', 'draftfileurl');
        $mform->setType('draftfileurl', PARAM_URL);

        
        $mform->addElement('static', 'imagemaker', '', $imagemaker);

        $this->add_action_buttons(false);
    }

}
