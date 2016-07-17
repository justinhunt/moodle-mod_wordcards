<?php
/**
 * Services definition.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

$functions = array(

    'mod_flashcards_mark_as_seen' => array(
        'classname'   => 'mod_flashcards_external',
        'methodname'  => 'mark_as_seen',
        'description' => 'Mark a term as seen.',
        'capabilities'=> 'mod/flashcards:view',
        'type'        => 'write',
        'ajax'        => true,
    ),

);
