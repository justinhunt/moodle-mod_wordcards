<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/16
 * Time: 19:31
 */

namespace mod_wordcards;

defined('MOODLE_INTERNAL') || die();

class constants {

    // component name, db tables, things that define app
    const M_COMPONENT = 'mod_wordcards';
    const M_DEFAULT_CLOUDPOODLL = 'cloud.poodll.com';
    const M_MODNAME = 'wordcards';
    const M_URL = '/mod/wordcards';
    const M_CLASS = 'mod_wordcards';
    const M_TABLE = 'wordcards';
    const M_TERMSTABLE = 'wordcards_terms';
    const M_ATTEMPTSTABLE = 'wordcards_progress';
    const M_AUTHTABLE = 'wordcards_auth';
    const M_GLOSSARYTABLE = 'glossary';
    const M_GLOSSARYENTRIESTABLE = 'glossary_entries';
    const M_ASSOCTABLE = 'wordcards_associations';
    const M_SEENTABLE = 'wordcards_seen';
    const M_MYWORDSTABLE = 'wordcards_my_words';

    const M_PLUGINSETTINGS = '/admin/settings.php?section=modsettingwordcards';
    const M_DEFLANG_OTHER = 'zz';
    const M_FRONTFACEFLIP_DEF = 0;
    const M_FRONTFACEFLIP_TERM = 1;

    const M_ANIM_FANCY = 0;
    const M_ANIM_PLAIN = 1;

    const M_LC_AUDIO_TERM = 0;
    const M_LC_AUDIO_DEF = 1;

    const M_MS_TERM_AT_TOP = 0;
    const M_MS_DEF_AT_TOP = 1;
    const M_SG_TERM_AS_ALIEN = 0;
    const M_SG_DEF_AS_ALIEN = 1;
    const M_WC_TERM_AS_READABLE = 0;
    const M_WC_MODELSENTENCE_AS_READABLE = 1;
    const MODE_STEPS = 0;
    const MODE_FREE = 1;
    const MODE_STEPSTHENFREE = 2;
    const MODE_SESSION = 3;
    const MODE_SESSIONTHENFREE = 4;
    const CURRENTMODE_STEPS = 0;
    const CURRENTMODE_FREE = 1;
    const CURRENTMODE_SESSION = 2;

    const FREEMODE_ACTIVITIES = ["wordpreview", "matchselect", "matchtype", "dictation", "speechcards", "listenchoose", "spacegame"];

    // grading options
    const M_GRADEHIGHEST = 0;
    const M_GRADELATEST = 2;

    const M_GRADELOWEST = 1;
    const M_GRADEAVERAGE = 3;
    const M_GRADENONE = 4;

    // languages
    const M_LANG_ENUS = 'en-US';
    const M_LANG_ENGB = 'en-GB';
    const M_LANG_ENAU = 'en-AU';
    const M_LANG_ENNZ = 'en-NZ';
    const M_LANG_ENZA = 'en-ZA';
    const M_LANG_ESUS = 'es-US';
    const M_LANG_FRCA = 'fr-CA';
    const M_LANG_FRFR = 'fr-FR';
    const M_LANG_ITIT = 'it-IT';
    const M_LANG_PTBR = 'pt-BR';
    const M_LANG_KOKR = 'ko-KR';
    const M_LANG_DEDE = 'de-DE';
    const M_LANG_HIIN = 'hi-IN';
    const M_LANG_ENIN = 'en-IN';
    const M_LANG_ESES = 'es-ES';

    const M_LANG_DADK = 'da-DK';
    const M_LANG_FILPH = 'fil-PH';

    const M_LANG_ARAE = 'ar-AE';
    const M_LANG_ARSA = 'ar-SA';
    const M_LANG_ZHCN = 'zh-CN';
    const M_LANG_NLNL = 'nl-NL';
    const M_LANG_NLBE = 'nl-BE';
    const M_LANG_ENIE = 'en-IE';
    const M_LANG_ENWL = 'en-WL';
    const M_LANG_ENAB = 'en-AB';
    const M_LANG_FAIR = 'fa-IR';
    const M_LANG_DECH = 'de-CH';
    const M_LANG_DEAT = 'de-AT';
    const M_LANG_HEIL = 'he-IL';
    const M_LANG_IDID = 'id-ID';
    const M_LANG_JAJP = 'ja-JP';
    const M_LANG_MSMY = 'ms-MY';
    const M_LANG_PTPT = 'pt-PT';
    const M_LANG_RURU = 'ru-RU';
    const M_LANG_TAIN = 'ta-IN';
    const M_LANG_TEIN = 'te-IN';
    const M_LANG_TRTR = 'tr-TR';
    const M_LANG_NONO = 'no-NO';
    const M_LANG_NBNO = 'nb-NO';
    const M_LANG_PLPL = 'pl-PL';
    const M_LANG_RORO = 'ro-RO';
    const M_LANG_SVSE = 'sv-SE';
    const M_LANG_UKUA = 'uk-UA';
    const M_LANG_EUES = 'eu-ES';
    const M_LANG_FIFI = 'fi-FI';
    const M_LANG_HUHU = 'hu-HU';
    const M_LANG_MINZ = 'mi-NZ';
    const M_LANG_BGBG = 'bg-BG';
    const M_LANG_CSCZ = 'cs-CZ';
    const M_LANG_ELGR = 'el-GR';
    const M_LANG_HRHR = 'hr-HR';
    const M_LANG_LTLT = 'lt-LT';
    const M_LANG_LVLV = 'lv-LV';
    const M_LANG_SKSK = 'sk-SK';
    const M_LANG_SLSI = 'sl-SI';
    const M_LANG_ISIS = 'is-IS';
    const M_LANG_MKMK = 'mk-MK';
    const M_LANG_SRRS = 'sr-RS';
    const M_LANG_VIVN = 'vi-VN';
    const M_LANG_OTHER = 'xx-XX';

    const TRANSCRIBER_NONE = 0;
    const TRANSCRIBER_AUTO = 1;
    const TRANSCRIBER_POODLL = 2;

    const M_USE_DATATABLES = 0;
    const M_USE_PAGEDTABLES = 1;

    const M_NO_TTS = 'none';
    const M_NEURALVOICES = ["Amy", "Emma", "Brian", "Arthur", "Olivia", "Aria", "Ayanda", "Ivy", "Joanna", "Kendra", "Kimberly",
    "Salli", "Joey", "Justin", "Kevin", "Matthew", "Camila", "Lupe", "Pedro", "Gabrielle", "Vicki", "Seoyeon", "Takumi", "Lucia",
    "Lea", "Remi", "Bianca", "Laura", "Kajal", "Suvi", "Liam", "Daniel", "Hannah", "Camila", "Ida", "Kazuha", "Tomoko", "Elin", "Hala", "Zayd", "Lisa"];

    const ALL_VOICES_NINGXIA = array(
        constants::M_LANG_ARAE => ['Hala'=>'Hala','Zayd'=>'Zayd'],
        constants::M_LANG_ARSA => ['Zeina'=>'Zeina'],
        constants::M_LANG_ZHCN => ['Zhiyu'=>'Zhiyu'],
        constants::M_LANG_DADK => ['Naja'=>'Naja','Mads'=>'Mads'],
        constants::M_LANG_NLNL => ["Ruben"=>"Ruben","Lotte"=>"Lotte","Laura"=>"Laura"],
        constants::M_LANG_NLBE => ["Lisa"=>"Lisa"],
        constants::M_LANG_ENUS => ['Joey'=>'Joey','Justin'=>'Justin','Matthew'=>'Matthew','Ivy'=>'Ivy',
            'Joanna'=>'Joanna','Kendra'=>'Kendra','Kimberly'=>'Kimberly','Salli'=>'Salli'],
        constants::M_LANG_ENGB => ['Brian'=>'Brian','Amy'=>'Amy', 'Emma'=>'Emma'],
        constants::M_LANG_ENAU => ['Russell'=>'Russell','Nicole'=>'Nicole'],
        constants::M_LANG_ENIN => ['Aditi'=>'Aditi', 'Raveena'=>'Raveena'],
        constants::M_LANG_ENWL => ["Geraint"=>"Geraint"],
        constants::M_LANG_FIFI => ['Suvi'=>'Suvi'],
        constants::M_LANG_FRCA => ['Chantal'=>'Chantal'],
        constants::M_LANG_FRFR => ['Mathieu'=>'Mathieu','Celine'=>'Celine', 'Lea'=>'Lea'],
        constants::M_LANG_DEDE => ['Hans'=>'Hans','Marlene'=>'Marlene', 'Vicki'=>'Vicki'],
        constants::M_LANG_DEAT => ['Hannah'=>'Hannah'],
        constants::M_LANG_HIIN => ["Aditi"=>"Aditi"],
        constants::M_LANG_ISIS => ['Dora' => 'Dora', 'Karl' => 'Karl'],
        constants::M_LANG_ITIT => ['Carla'=>'Carla',  'Bianca'=>'Bianca', 'Giorgio'=>'Giorgio'],
        constants::M_LANG_JAJP => ['Takumi'=>'Takumi','Mizuki'=>'Mizuki'],
        constants::M_LANG_KOKR => ['Seoyeon'=>'Seoyeon'],
        constants::M_LANG_NONO => ['Liv'=>'Liv'],
        constants::M_LANG_PLPL => ['Ewa'=>'Ewa','Maja'=>'Maja','Jacek'=>'Jacek','Jan'=>'Jan'],
        constants::M_LANG_PTBR => ['Ricardo'=>'Ricardo', 'Vitoria'=>'Vitoria','Camila'=>'Camila'],
        constants::M_LANG_PTPT => ["Ines"=>"Ines",'Cristiano'=>'Cristiano'],
        constants::M_LANG_RORO => ['Carmen'=>'Carmen'],
        constants::M_LANG_RURU => ["Tatyana"=>"Tatyana","Maxim"=>"Maxim"],
        constants::M_LANG_ESUS => ['Miguel'=>'Miguel','Penelope'=>'Penelope','Lupe'=>'Lupe','Pedro'=>'Pedro'],
        constants::M_LANG_ESES => [ 'Enrique'=>'Enrique', 'Conchita'=>'Conchita', 'Lucia'=>'Lucia'],
        constants::M_LANG_SVSE => ['Astrid'=>'Astrid'],
        constants::M_LANG_TRTR => ['Filiz'=>'Filiz'],
    );
    
    const ALL_VOICES = [
        self::M_LANG_ARAE => ['Hala' => 'Hala', 'Zayd' => 'Zayd'],
        self::M_LANG_ARSA => ['Zeina' => 'Zeina', 'ar-XA-Wavenet-B' => 'Amir_g', 'ar-XA-Wavenet-A' => 'Salma_g'],
        self::M_LANG_BGBG => ['bg-BG-Standard-A' => 'Mila_g'], // nikolai
        self::M_LANG_HRHR => ['hr-HR-Whisper-alloy' => 'Marko', 'hr-HR-Whisper-shimmer' => 'Ivana'],
        self::M_LANG_ZHCN => ['Zhiyu' => 'Zhiyu'],
        self::M_LANG_CSCZ => ['cs-CZ-Wavenet-A' => 'Zuzana_g', 'cs-CZ-Standard-A' => 'Karolina_g'],
        self::M_LANG_DADK => ['Naja' => 'Naja', 'Mads' => 'Mads'],
        self::M_LANG_NLNL => ["Ruben" => "Ruben", "Lotte" => "Lotte", "Laura" => "Laura"],
        self::M_LANG_NLBE => ["nl-BE-Wavenet-B" => "Marc_g", "nl-BE-Wavenet-A" => "Marie_g", "Lisa" => "Lisa"],
        // constants::M_LANG_DECH => [],
        self::M_LANG_ENUS => ['Joey' => 'Joey', 'Justin' => 'Justin', 'Kevin' => 'Kevin', 'Matthew' => 'Matthew', 'Ivy' => 'Ivy',
            'Joanna' => 'Joanna', 'Kendra' => 'Kendra', 'Kimberly' => 'Kimberly', 'Salli' => 'Salli',
            'en-US-Whisper-alloy' => 'Ricky', 'en-US-Whisper-onyx' => 'Ed', 'en-US-Whisper-nova' => 'Tiffany', 'en-US-Whisper-shimmer' => 'Tammy'],
        self::M_LANG_ENGB => ['Brian' => 'Brian', 'Amy' => 'Amy', 'Emma' => 'Emma', 'Arthur' => 'Arthur'],
        self::M_LANG_ENAU => ['Russell' => 'Russell', 'Nicole' => 'Nicole', 'Olivia' => 'Olivia'],
        self::M_LANG_ENNZ => ['Aria' => 'Aria'],
        self::M_LANG_ENZA => ['Ayanda' => 'Ayanda'],
        self::M_LANG_ENIN => ['Aditi' => 'Aditi', 'Raveena' => 'Raveena', 'Kajal' => 'Kajal'],
        // constants::M_LANG_ENIE => [],
        self::M_LANG_ENWL => ["Geraint" => "Geraint"],
        // constants::M_LANG_ENAB => [],

        // constants::M_LANG_FAIR => [],
        self::M_LANG_FILPH => ['fil-PH-Wavenet-A' => 'Darna_g', 'fil-PH-Wavenet-B' => 'Reyna_g', 'fil-PH-Wavenet-C' => 'Bayani_g', 'fil-PH-Wavenet-D' => 'Ernesto_g'],
        self::M_LANG_FIFI => ['Suvi' => 'Suvi', 'fi-FI-Wavenet-A' => 'Kaarina_g'],
        self::M_LANG_FRCA => ['Chantal' => 'Chantal', 'Gabrielle' => 'Gabrielle', 'Liam' => 'Liam'],
        self::M_LANG_FRFR => ['Mathieu' => 'Mathieu', 'Celine' => 'Celine', 'Lea' => 'Lea', 'Remi' => 'Remi'],
        self::M_LANG_DEDE => ['Hans' => 'Hans', 'Marlene' => 'Marlene', 'Vicki' => 'Vicki', 'Daniel' => 'Daniel'],
        self::M_LANG_DEAT => ['Hannah' => 'Hannah'],
        self::M_LANG_ELGR => ['el-GR-Wavenet-A' => 'Sophia_g', 'el-GR-Standard-A' => 'Isabella_g'],
        self::M_LANG_HIIN => ["Aditi" => "Aditi"],
        self::M_LANG_HEIL => ['he-IL-Wavenet-A' => 'Sarah_g', 'he-IL-Wavenet-B' => 'Noah_g'],
        self::M_LANG_HUHU => ['hu-HU-Wavenet-A' => 'Eszter_g'],

        self::M_LANG_IDID => ['id-ID-Wavenet-A' => 'Guntur_g', 'id-ID-Wavenet-B' => 'Bhoomik_g'],
        self::M_LANG_ISIS => ['Dora' => 'Dora', 'Karl' => 'Karl'],
        self::M_LANG_ITIT => ['Carla' => 'Carla',  'Bianca' => 'Bianca', 'Giorgio' => 'Giorgio'],
        self::M_LANG_JAJP => ['Takumi' => 'Takumi', 'Mizuki' => 'Mizuki', 'Kazuha' => 'Kazuha', 'Tomoko' => 'Tomoko'],
        self::M_LANG_KOKR => ['Seoyeon' => 'Seoyeon'],
        self::M_LANG_LVLV => ['lv-LV-Standard-A' => 'Janis_g'],
        self::M_LANG_LTLT => ['lt-LT-Standard-A' => 'Matas_g'],
        self::M_LANG_MINZ => ['mi-NZ-Whisper-alloy' => 'Tane', 'mi-NZ-Whisper-shimmer' => 'Aroha'],
        self::M_LANG_MKMK => ['mk-MK-Whisper-alloy' => 'Trajko', 'mk-MK-Whisper-shimmer' => 'Marija'],
        self::M_LANG_MSMY => ['ms-MY-Whisper-alloy' => 'Afsah', 'ms-MY-Whisper-shimmer' => 'Siti'],
        self::M_LANG_NONO => ['Liv' => 'Liv', 'Ida' => 'Ida', 'nb-NO-Wavenet-B' => 'Lars_g', 'nb-NO-Wavenet-A' => 'Hedda_g', 'nb-NO-Wavenet-D' => 'Anders_g'],
        self::M_LANG_PLPL => ['Ewa' => 'Ewa', 'Maja' => 'Maja', 'Jacek' => 'Jacek', 'Jan' => 'Jan'],
        self::M_LANG_PTBR => ['Ricardo' => 'Ricardo', 'Vitoria' => 'Vitoria', 'Camila' => 'Camila'],
        self::M_LANG_PTPT => ["Ines" => "Ines", 'Cristiano' => 'Cristiano'],
        self::M_LANG_RORO => ['Carmen' => 'Carmen', 'ro-RO-Wavenet-A' => 'Sorina_g'],
        self::M_LANG_RURU => ["Tatyana" => "Tatyana", "Maxim" => "Maxim"],
        self::M_LANG_ESUS => ['Miguel' => 'Miguel', 'Penelope' => 'Penelope', 'Lupe' => 'Lupe', 'Pedro' => 'Pedro'],
        self::M_LANG_ESES => [ 'Enrique' => 'Enrique', 'Conchita' => 'Conchita', 'Lucia' => 'Lucia'],
        self::M_LANG_SVSE => ['Astrid' => 'Astrid', 'Elin' => 'Elin'],
        self::M_LANG_SKSK => ['sk-SK-Wavenet-A' => 'Laura_g', 'sk-SK-Standard-A' => 'Natalia_g'],
        self::M_LANG_SLSI => ['sl-SI-Whisper-alloy' => 'Vid', 'sl-SI-Whisper-shimmer' => 'Pia'],
        self::M_LANG_SRRS => ['sr-RS-Standard-A' => 'Milena_g'],
        self::M_LANG_TAIN => ['ta-IN-Wavenet-A' => 'Dyuthi_g', 'ta-IN-Wavenet-B' => 'Bhoomik_g'],
        self::M_LANG_TEIN => ['te-IN-Standard-A' => 'Anandi_g', 'te-IN-Standard-B' => 'Kai_g'],
        self::M_LANG_TRTR => ['Filiz' => 'Filiz'],
        self::M_LANG_UKUA => ['uk-UA-Wavenet-A' => 'Katya_g'],
        self::M_LANG_VIVN => ['vi-VN-Wavenet-A' => 'Huyen_g', 'vi-VN-Wavenet-B' => 'Duy_g'],
    ];

    const MS_TRANSLATE_LANGCODES = [
    'af' => 'Afrikaans',
    'ar' => 'Arabic',
    'bn' => 'Bangla',
    'bs' => 'Bosnian',
    'bg' => 'Bulgarian',
    'ca' => 'Catalan',
    'cs' => 'Czech',
    'cy' => 'Welsh',
    'da' => 'Danish',
    'de' => 'German',
    'el' => 'Greek',
    'en' => 'English',
    'es' => 'Spanish',
    'et' => 'Estonian',
    'fa' => 'Persian',
    'fi' => 'Finnish',
    'fr' => 'French',
    'ht' => 'Haitian Creole',
    'he' => 'Hebrew',
    'hi' => 'Hindi',
    'hr' => 'Croatian',
    'hu' => 'Hungarian',
    'id' => 'Indonesian',
    'is' => 'Icelandic',
    'it' => 'Italian',
    'ja' => 'Japanese',
    'ko' => 'Korean',
    'lt' => 'Lithuanian',
    'lv' => 'Latvian',
    'mww' => 'Hmong Daw',
    'ms' => 'Malay',
    'mt' => 'Maltese',
    'nl' => 'Dutch',
    'nb' => 'Norwegian',
    'pl' => 'Polish',
    'pt' => 'Portuguese',
    'ro' => 'Romanian',
    'ru' => 'Russian',
    'sr-Latn' => 'Serbian (Latin)',
    'sk' => 'Slovak',
    'sl' => 'Slovenian',
    'sv' => 'Swedish',
    'ta' => 'Tamil',
    'th' => 'Thai',
    'tr' => 'Turkish',
    'uk' => 'Ukrainian',
    'ur' => 'Urdu',
    'vi' => 'Vietnamese',
    'zh-Hans' => 'Chinese Simplified',
    ];

    const M_PUSHMODE_NONE = 0;
    const M_PUSHMODE_MODULENAME = 1;
    const M_PUSHMODE_COURSE = 2;
    const M_PUSHMODE_SITE = 3;
    const M_PUSH_NONE = 0;
    const M_PUSH_TRANSCRIBER = 1;
    const M_PUSH_SHOWLANGCHOOSER = 2;
    const M_PUSH_LEARNPOINT = 3;
    const M_PUSH_MAXATTEMPTS = 4;
    const M_PUSH_STEPSMODEOPTIONS = 5;
    const M_PUSH_FREEMODEOPTIONS = 6;
    const M_PUSH_JOURNEYMODE = 7;
    const M_PUSH_VIDEOEXAMPLES = 8;
    const M_PUSH_SHOWIMAGEFLIP = 9;
    const M_PUSH_FRONTFACEFLIP = 10;
    const M_PUSH_LCOPTIONS = 11;
    const M_PUSH_MSOPTIONS = 12;
    const M_PUSH_SGOPTIONS = 13;


}
