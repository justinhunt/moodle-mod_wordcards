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
 * Import form.
 *
 * @package    mod_wordcards
 * @copyright  2016 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
class mod_wordcards_form_import extends moodleform {

   public function definition() {
        $mform = $this->_form;
        $leftover_rows = $this->_customdata['leftover_rows'];
        
        $delimiter_options=array('delim_tab'=>get_string('delim_tab','mod_wordcards'),
         	'delim_comma'=>get_string('delim_comma','mod_wordcards'),
         	'delim_pipe'=>get_string('delim_pipe','mod_wordcards')
         );
        $mform->addElement('select', 'delimiter', get_string('delimiter', 'mod_wordcards'),$delimiter_options);
        $mform->setType('delimiter', PARAM_NOTAGS);
        $mform->setDefault('delimiter', 'delim_pipe');
        $mform->addRule('delimiter', null, 'required', null, 'client');

        
        $mform->addElement('textarea', 'importdata', get_string('importdata', 'mod_wordcards'), array('style'=>'width: 100%; max-width: 1200px;'));
        $mform->setDefault('importdata', $leftover_rows);
        $mform->setType('importdata', PARAM_NOTAGS);
        $mform->addRule('importdata', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('importwords', constants::M_COMPONENT));
    }

}
