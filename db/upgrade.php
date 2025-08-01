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

// This file keeps track of upgrades to
// the wordcards module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

defined('MOODLE_INTERNAL') || die();

use \mod_wordcards\utils;
use \mod_wordcards\constants;

function xmldb_wordcards_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2016080200) {

        // Define field skipglobal to be added to wordcards.
        $table = new xmldb_table('wordcards');
        $field = new xmldb_field('skipglobal', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'timemodified');

        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2016080200, 'wordcards');
    }

    if ($oldversion < 2016080500) {

        // Define field finishedscattermsg to be added to wordcards.
        $table = new xmldb_table('wordcards');
        $field = new xmldb_field('finishedscattermsg', XMLDB_TYPE_TEXT, null, null, null, null, null, 'skipglobal');

        // Conditionally launch add field finishedscattermsg.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

         // Define field completedmsg to be added to wordcards.
        $table = new xmldb_table('wordcards');
        $field = new xmldb_field('completedmsg', XMLDB_TYPE_TEXT, null, null, null, null, null, 'finishedscattermsg');

        // Conditionally launch add field completedmsg.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2016080500, 'wordcards');
    }

    if ($oldversion < 2019041200) {

        // Define field skipglobal to be added to wordcards.
        $table = new xmldb_table('wordcards');
        $field = new xmldb_field('localpracticetype', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'timemodified');

        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        $field = new xmldb_field('globalpracticetype', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'timemodified');

        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2019041200, 'wordcards');
    }
    if ($oldversion < 2019091401) {

        // Define field image to be added to wordcard terms.
        $table = new xmldb_table('wordcards_terms');
        $field = new xmldb_field('image', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        //define audio to be added to wordcard terms
        $field = new xmldb_field('audio', XMLDB_TYPE_TEXT, null, null, null, null, null);
        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2019091401, 'wordcards');
    }
    if($oldversion<2019091402) {

        // Define field ttslanguage to be added to wordcard terms.
        $table = new xmldb_table('wordcards');
        $field = new xmldb_field('ttslanguage', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 'en-US');
        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field ttslanguage to be added to wordcard terms.
        $table = new xmldb_table('wordcards_terms');
        $field = new xmldb_field('ttsvoice', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 'Kendra');
        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2019091402, 'wordcards');
    }
    if($oldversion<2019091403) {


        // Define field alternates to be added to wordcard terms.
        $table = new xmldb_table('wordcards_terms');
        $field = new xmldb_field('alternates', XMLDB_TYPE_TEXT, null, null, null, null, null);
        // Conditionally launch add field skipglobal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2019091403, 'wordcards');
    }

    if($oldversion<2019120501) {

        $table = new xmldb_table('wordcards');
        $field = new xmldb_field('skipglobal', XMLDB_TYPE_INTEGER, '1', null, null, null, '1');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'skipreview');
        }
        $field = new xmldb_field('finishedscattermsg', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'finishedstepmsg');
        }
        $field = new xmldb_field('finishedscattermsg', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'finishedstepmsg');
        }
        $field = new xmldb_field('localtermcount', XMLDB_TYPE_INTEGER, '2');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'step1termcount');
        }
        $field = new xmldb_field('globaltermcount', XMLDB_TYPE_INTEGER, '2');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'step2termcount');
        }
        $field = new xmldb_field('localpracticetype', XMLDB_TYPE_INTEGER, '2');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'step1practicetype');
        }
        $field = new xmldb_field('globalpracticetype', XMLDB_TYPE_INTEGER, '2');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'step2practicetype');
        }


        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2019120501, 'wordcards');
    }

    if($oldversion<2019120601) {

        $table = new xmldb_table('wordcards');
        $fields= array();
        $fields[] = new xmldb_field('step3practicetype', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        $fields[] = new xmldb_field('step4practicetype', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        $fields[] = new xmldb_field('step5practicetype', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        $fields[] = new xmldb_field('step3termcount', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        $fields[] = new xmldb_field('step4termcount', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        $fields[] = new xmldb_field('step5termcount', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');

        foreach($fields as $field){
            // Conditionally launch add field skipglobal.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2019120601, 'wordcards');
     }

    if($oldversion<2020050204) {

        $table = new xmldb_table('wordcards');
        $fields= array();
        $fields[] = new xmldb_field('maxattempts', XMLDB_TYPE_INTEGER, '10', null, true, null, '0');

        foreach($fields as $field){
            // Conditionally launch add field skipglobal.
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2020050204, 'wordcards');
    }

    if($oldversion<2020050205) {
        $table = new xmldb_table('wordcards_progress');
        $index = new xmldb_index('moduser', XMLDB_INDEX_UNIQUE, array('modid', 'userid'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2020050205, 'wordcards');
    }

    if($oldversion<2020100200) {

        //we added these fields in install.xml but not in upgrade.php in may

        //progress
        $ptable = new xmldb_table('wordcards_progress');
        $pfields = array();
        $pfields[] = new xmldb_field('grade1', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $pfields[] = new xmldb_field('grade2', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $pfields[] = new xmldb_field('grade3', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $pfields[] = new xmldb_field('grade4', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $pfields[] = new xmldb_field('grade5', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $pfields[] = new xmldb_field('totalgrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $pfields[] = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        foreach($pfields as $pfield){
            // Conditionally launch add field .
            if (!$dbman->field_exists($ptable, $pfield)) {
                $dbman->add_field($ptable, $pfield);
            }
        }

        //wordcards
        $wtable = new xmldb_table('wordcards');
        $wfields = array();
        $wfields[] = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $wfields[] = new xmldb_field('gradeoptions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $wfields[] = new xmldb_field('mingrade', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        foreach($wfields as $wfield){
            // Conditionally launch add field .
            if (!$dbman->field_exists($wtable, $wfield)) {
                $dbman->add_field($wtable, $wfield);
            }
        }


        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2020100200, 'wordcards');
    }

    if($oldversion<2020110900){
        $table = new xmldb_table('wordcards_terms');
        $fields = array();
        $fields[] = new xmldb_field('model_sentence', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $fields[] = new xmldb_field('model_sentence_audio', XMLDB_TYPE_TEXT, null, null, null, null, null);
        foreach($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        // Wordcards savepoint reached.
        upgrade_mod_savepoint(true, 2020110900, 'wordcards');
    }

    // Add passage hashcode to wordcards table
    if ($oldversion < 2020111000) {
        $table = new xmldb_table('wordcards');
        $fields = array();

        // Define field expiredays to be added to readaloud
        $fields[] = new xmldb_field('passagehash', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, null, null);
        $fields[] = new xmldb_field('hashisold', XMLDB_TYPE_INTEGER, '2', null, null, null, '0');
        foreach($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2020111001, 'wordcards');
    }

    // Add foriframe option to wordcards table
    if ($oldversion < 2021053100) {
        $table = new xmldb_table('wordcards');


        // Define field foriframe to be added to wordcards
        $field= new xmldb_field('foriframe', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // add foriframe field to wordcards table
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2021053100, 'wordcards');
    }

    // Add showimagesonflip option to wordcards table, and phonetics to terms table
    if ($oldversion < 2021083100) {
        $table = new xmldb_table('wordcards');

        // Define field showimagesonflip to be added to wordcards
        $fields=[];
        $fields[]= new xmldb_field('showimageflip', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 1);
        $fields[]= new xmldb_field('frontfaceflip', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // add showimagesonflip field to wordcards table
        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('wordcards_terms');

        //  Define fields phonetic and phoneticms to be added to wordcards
        $fields=[];
        $fields[] = new xmldb_field('phonetic', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[] = new xmldb_field('phoneticms', XMLDB_TYPE_TEXT, null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2021083100, 'wordcards');
    }


    if ($oldversion < 2021110500) {

        $table = new xmldb_table('wordcards');

        //  Define field trancriber to be added to wordcards
        $fields=[];
        $fields[]= new xmldb_field('transcriber', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 1);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2021110500, 'wordcards');
    }

    if ($oldversion < 2022012000) {
        $DB->set_field('wordcards_terms','ttsvoice','Seoyeon',array('ttsvoice'=>'Seoyan'));
        upgrade_mod_savepoint(true, 2022012000, 'wordcards');
    }

    if ($oldversion < 2022020500) {
        $table = new xmldb_table('wordcards');
        // Define field foriframe to be added to wordcards
        $field= new xmldb_field('deflanguage', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, null, null,'en');

        // add  field to wordcards table
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        //Add translation fields to wordcards
        $table = new xmldb_table('wordcards_terms');
        $fields=[];
        $fields[] = new xmldb_field('translations', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[]= new xmldb_field('sourcedef', XMLDB_TYPE_TEXT, null, null, null, null);
        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2022020500, 'wordcards');
    }

	  if ($oldversion < 2022020800) {
        $table = new xmldb_table(constants::M_TABLE);

        $fields=[];
        $fields[] = new xmldb_field('viewstart', XMLDB_TYPE_INTEGER, 10, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('viewend', XMLDB_TYPE_INTEGER, 10, XMLDB_NOTNULL, null, 0);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2022020800, 'wordcards');
    }

    if ($oldversion < 2022021500) {
        $table = new xmldb_table(constants::M_TABLE);

        $fields=[];
        $fields[] = new xmldb_field('journeymode', XMLDB_TYPE_INTEGER, 4, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 2);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2022021500, 'wordcards');
    }

    if ($oldversion < 2022022701) {

        // Define table wordcards_my_words to be created.
        $table = new xmldb_table('wordcards_my_words');

        // Adding fields to table wordcards_my_words.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('termid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table wordcards_my_words.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('termid', XMLDB_KEY_FOREIGN, ['termid'], 'wordcards_terms', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Conditionally launch create table for wordcards_my_words.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2022022701, 'wordcards');
    }

    if ($oldversion < 2022031300) {
        $table = new xmldb_table(constants::M_TABLE);
        $fields=[];
        $fields[] = new xmldb_field('lcoptions', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, constants::M_LC_AUDIO_DEF);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2022031300, 'wordcards');
    }

    //add missing defaults on wordcards
    if ($oldversion < 2022060500) {
        $table = new xmldb_table(constants::M_TABLE);

        $vfields=[];
        $vfields[] = new xmldb_field('viewstart', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED,null, null, 0);
        $vfields[] = new xmldb_field('viewend', XMLDB_TYPE_INTEGER, 10,XMLDB_UNSIGNED, null, null, 0);

        // Add fields
        foreach ($vfields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2022060500, 'wordcards');
    }

    if ($oldversion < 2023051201) {
        $table = new xmldb_table('wordcards');
        // Define field foriframe to be added to wordcards
        $fields=[];
        $fields[] = new xmldb_field('deflanguage', XMLDB_TYPE_CHAR, 255, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 'en');
        $fields[] = new xmldb_field('viewstart', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED,XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('viewend', XMLDB_TYPE_INTEGER, 10,XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $DB->set_field('wordcards','deflanguage','en',['deflanguage'=>null]);
        $DB->set_field('wordcards','viewstart',0,['viewstart'=>null]);
        $DB->set_field('wordcards','viewend',0,['viewend'=>null]);

        // Alter fields
        foreach ($fields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2023051201, 'wordcards');
    }

    if ($oldversion < 2023090301) {
        $table = new xmldb_table('wordcards');
        // Define field foriframe to be added to wordcards
        $fields=[];
        $fields[] = new xmldb_field('videoexamples', XMLDB_TYPE_INTEGER, 2,XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('learnpoint', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 1);


        // Alter fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2023090301, 'wordcards');
    }

    if($oldversion < 2023092600){
        //The norwegian language-locale code nb-no is not supported by all STT engines in Poodll, and no-no is. So updating
        $DB->set_field(constants::M_TABLE,'ttslanguage',constants::M_LANG_NONO,['ttslanguage'=>constants::M_LANG_NBNO]);
        upgrade_mod_savepoint(true, 2023092600, 'wordcards');
    }

    if ($oldversion < 2024042700) {
        $table = new xmldb_table(constants::M_TABLE);
        $fields = [];
        $fields[] = new xmldb_field('msoptions', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, constants::M_MS_DEF_AT_TOP);
        $fields[] = new xmldb_field('sgoptions', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, constants::M_SG_TERM_AS_ALIEN);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2024042700, 'wordcards');
    }

    if ($oldversion < 2024101500) {
        $table = new xmldb_table('wordcards');
        // Define field freemodeoptions to be added to wordcards
        $field = new xmldb_field('freemodeoptions', XMLDB_TYPE_TEXT, null, null, null, null);

        // add  field to wordcards table
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2024101500, 'wordcards');
    }

    if ($oldversion < 2024111101) {
        $table = new xmldb_table(constants::M_TABLE);
        $fields = [];
        $fields[] = new xmldb_field('scoptions', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, constants::M_WC_TERM_AS_READABLE);
 
        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2024111101, 'wordcards');
    }

    if ($oldversion < 2024111203) {
        $table = new xmldb_table('wordcards');
        // Define field foriframe to be added to wordcards
        $fields = [];
        $fields[] = new xmldb_field('completionwhenfinish', XMLDB_TYPE_INTEGER, 2,XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('completionwhenlearned', XMLDB_TYPE_INTEGER, 2,XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('showlangchooser', XMLDB_TYPE_INTEGER, 2,XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('masterinstance', XMLDB_TYPE_INTEGER, 10,XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // Alter fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2024111203, 'wordcards');
    }

    $newversion = 2024120700;
    if ($oldversion < $newversion) {
        // Add auth table.
        $table = new xmldb_table('wordcards_auth');

        // Add fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('secret', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);

        // Add keys and index.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('user_id', XMLDB_INDEX_UNIQUE, ['user_id']);

        // Create table if it does not exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, $newversion, 'wordcards');
    }

    if ($oldversion < 2025070400) {
        $table = new xmldb_table(constants::M_ASSOCTABLE);
        $fields = [];
        $fields[] = new xmldb_field('selfclaim', XMLDB_TYPE_INTEGER, 2, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2025070400, 'wordcards');
    }

    return true;
}