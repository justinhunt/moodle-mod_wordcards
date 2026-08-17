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
 * Image Wizard form.
 *
 * @package    mod_wordcards
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
