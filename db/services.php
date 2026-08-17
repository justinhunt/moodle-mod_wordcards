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
 * Services definition.
 *
 * @package    mod_wordcards
 * @author     Frédéric Massart - FMCorz.net
 * @copyright  2016 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_wordcards_mark_as_seen' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'mark_as_seen',
        'description' => 'Mark a term as seen.',
        'capabilities'=> 'mod/wordcards:view',
        'type'        => 'write',
        'ajax'        => true,
    ),

    'mod_wordcards_report_successful_association' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'report_successful_association',
        'description' => 'Reports a successful association of terms.',
        'capabilities'=> 'mod/wordcards:view',
        'type'        => 'write',
        'ajax'        => true,
    ),

    'mod_wordcards_report_failed_association' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'report_failed_association',
        'description' => 'Reports a failed association of terms.',
        'capabilities'=> 'mod/wordcards:view',
        'type'        => 'write',
        'ajax'        => true,
    ),

    'mod_wordcards_check_by_phonetic' => array(
            'classname'   => 'mod_wordcards_external',
            'methodname'  => 'check_by_phonetic',
            'description' => 'compares a spoken phrase to a correct phrase by phoneme' ,
            'capabilities'=> 'mod/wordcards:view',
            'type'        => 'read',
            'ajax'        => true,
    ),

    'mod_wordcards_report_step_grade' => array(
            'classname'   => 'mod_wordcards_external',
            'methodname'  => 'report_step_grade',
            'description' => 'Reports the grade of a step',
            'capabilities'=> 'mod/wordcards:view',
            'type'        => 'write',
            'ajax'        => true,
    ),
    'mod_wordcards_submit_mform' => array(
                'classname'   => 'mod_wordcards_external',
                'methodname'  => 'submit_mform',
                'description' => 'saves or edits term/def form',
                'capabilities'=> 'mod/wordcards:manage',
                'type'        => 'write',
                'ajax'        => true,
     ),
    'mod_wordcards_submit_newterm' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'submit_newterm',
        'description' => 'saves a new term in the db',
        'capabilities'=> 'mod/wordcards:manage',
        'type'        => 'write',
        'ajax'        => true,
    ),
    'mod_wordcards_search_dictionary' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'search_dictionary',
        'description' => 'search dictionary term in the db',
        'capabilities'=> 'mod/wordcards:manage',
        'type'        => 'read',
        'ajax'        => true,
    ),

    'mod_wordcards_set_my_words' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'set_my_words',
        'description' => 'Set a word as being in my words or not',
        'capabilities'=> 'mod/wordcards:view',
        'type'        => 'write',
        'ajax'        => true,
    ),

    'mod_wordcards_report_successful_learnclaim' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'report_successful_learnclaim',
        'description' => 'Reports a successful claim that a term is learned(or known).',
        'capabilities'=> 'mod/wordcards:view',
        'type'        => 'write',
        'ajax'        => true,
    ),

    'mod_wordcards_set_user_preference' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'set_user_preference',
        'description' => 'Sets a wordcards user preference',
        'capabilities'=> 'mod/wordcards:view',
        'type'        => 'write',
        'ajax'        => true,
    ),

     'mod_wordcards_generate_image' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'generate_image',
        'description' => 'Generates an image based on the provided prompt',
        'capabilities' => 'mod/wordcards:manage',
        'type'        => 'write',
        'ajax'        => true,
    ),

    'mod_wordcards_generate_bulk_images' => array(
        'classname'   => 'mod_wordcards_external',
        'methodname'  => 'generate_bulk_images',
        'description' => 'Generates images based on the provided prompts',
        'capabilities' => 'mod/wordcards:manage',
        'type'        => 'write',
        'ajax'        => true,
    ),
);
