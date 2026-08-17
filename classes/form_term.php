<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Term form.
 *
 * @package    mod_wordcards
 * @author     Frédéric Massart - FMCorz.net
 * @copyright  2016 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $bigwidth = array('style'=>'width: 100%');

        $mform->addElement('hidden', 'termid');
        $mform->setType('termid', PARAM_INT);
        $mform->setConstant('termid', $termid);

        $mform->addElement('text', 'term', get_string('term', constants::M_COMPONENT),$bigwidth);
        $mform->setType('term', PARAM_NOTAGS);
        $mform->addHelpButton('term', 'term', constants::M_COMPONENT);
        $mform->addRule('term', null, 'required', null, 'client');
        $mform->addRule('term', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
    
        $mform->addElement('textarea', 'definition', get_string('definition', constants::M_COMPONENT),$bigwidth);
        $mform->setType('definition', PARAM_NOTAGS);
        $mform->addHelpButton('definition', 'definition', constants::M_COMPONENT);
        $mform->addRule('definition', null, 'required', null, 'client');
    
        $mform->addElement('text', 'model_sentence', get_string('model_sentence', constants::M_COMPONENT),$bigwidth);
        $mform->setType('model_sentence', PARAM_NOTAGS);
        $mform->addHelpButton('model_sentence', 'model_sentence', constants::M_COMPONENT);
        $mform->addRule('model_sentence', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
 
        $mform->addElement('textarea', 'alternates', get_string('alternates', constants::M_COMPONENT),$bigwidth);
        $mform->addHelpButton('alternates', 'alternates', constants::M_COMPONENT);
        $mform->setType('alternates', PARAM_NOTAGS);

        $voices=utils::get_tts_voices($ttslanguage, false);
        $mform->addElement('select', 'ttsvoice', get_string('ttsvoice', constants::M_COMPONENT),
                $voices);
        $mform->addHelpButton('ttsvoice', 'ttsvoice', constants::M_COMPONENT);

        $mform->addElement('header', 'audioandimages', get_string('audioandimages', constants::M_COMPONENT));
        $mform->setExpanded('audioandimages', false);

        $filemanageropts = utils::fetch_filemanager_opts('audio');
        $mform->addElement('filemanager', 'audio_filemanager', get_string('audiofile', constants::M_COMPONENT), null,
                $filemanageropts);
        $mform->addHelpButton('audio_filemanager', 'audiofile', constants::M_COMPONENT);
        
        $filemanageropts = utils::fetch_filemanager_opts('audio');
        $mform->addElement('filemanager', 'model_sentence_audio_filemanager', get_string('model_sentence_audio', constants::M_COMPONENT), null,
                $filemanageropts);

        $mform->addHelpButton('audio_filemanager', 'audiofile', constants::M_COMPONENT);

        $filemanageropts = utils::fetch_filemanager_opts('image');
        $mform->addElement('filemanager', 'image_filemanager', get_string('imagefile', constants::M_COMPONENT), null,
                $filemanageropts);
        $mform->addHelpButton('image_filemanager', 'imagefile', constants::M_COMPONENT);

        $this->add_action_buttons(false);
    }

}
