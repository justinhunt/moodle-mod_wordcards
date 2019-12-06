<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/16
 * Time: 19:31
 */

namespace mod_wordcards;

defined('MOODLE_INTERNAL') || die();

class constants
{
//component name, db tables, things that define app
const M_COMPONENT='mod_wordcards';
const M_MODNAME='wordcards';
const M_URL='/mod/wordcards';
const M_CLASS='mod_wordcards';
const M_PLUGINSETTINGS ='/admin/settings.php?section=modsettingwordcards';

//languages
const M_LANG_ENUS = 'en-US';
const M_LANG_ENUK = 'en-GB';
const M_LANG_ENAU = 'en-AU';
const M_LANG_ENIN = 'en-IN';
const M_LANG_ESUS = 'es-US';
const M_LANG_ESES = 'es-ES';
const M_LANG_FRCA = 'fr-CA';
const M_LANG_FRFR = 'fr-FR';
const M_LANG_DEDE = 'de-DE';
const M_LANG_ITIT = 'it-IT';
const M_LANG_PTBR = 'pt-BR';

const TRANSCRIBER_NONE = 0;
const TRANSCRIBER_AMAZONTRANSCRIBE = 1;
const TRANSCRIBER_GOOGLECLOUDSPEECH = 2;
const TRANSCRIBER_GOOGLECHROME = 3;

}