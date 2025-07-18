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
 * Renderer.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

/**
 * Renderer class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

namespace mod_wordcards\output;

use mod_wordcards\my_words_pool;
use mod_wordcards\utils;
use mod_wordcards\constants;

class renderer extends \plugin_renderer_base
{

    public function definitions_page_data(\mod_wordcards_module $mod, $definitions, $embed = 0)
    {
        global $USER;

        $mywordspool = new my_words_pool($mod->get_course()->id);

        // Randomize definitions.
        shuffle($definitions);

        $firstcardset = false;
        foreach ($definitions as $def) {
            // we cheat here , and add an index to avoid flicker before we apply the show/hide to flashcards
            if (!$firstcardset) {
                $def->isfirstcard = true;
                $firstcardset = true;
            }
            // make sure each definition has a voice
            if ($def->ttsvoice == 'Auto' || $def->ttsvoice == '') {
                $def->ttsvoice = utils::fetch_auto_voice($mod->get_mod()->ttslanguage);
            }
            // Add flag to show if it's in "My words" or not.
            $def->isinmywords = $mywordspool->has_term($def->id);
        }

        // is this word learned?
        $definitions = $mod->insert_learned_state($definitions);

        // attempt info
        $canattempt = $mod->can_attempt();
        $attempts = $mod->get_attempts();
        $canmanage = $mod->can_manage();
        if ($attempts) {
            $attemptcount = count($attempts);
        } else {
            $attemptcount = 0;
        }
        $maxattempts = $mod->get_mod()->maxattempts;
        $isreattempt = $attemptcount > 0 && $canattempt && !$canmanage;
        if ($isreattempt) {
            $nextaction = 'reattempt';
            $reattempt = 1;
            // $nextbuttontext=get_string('reattempt',constants::M_COMPONENT);
            // some felt "try again" was confusing here ... not sure .. but ok justin 20231118
            $nextbuttontext = get_string('continue', constants::M_COMPONENT);
        } else if ($attemptcount == 0 || $canattempt) {
            $nextaction = 'attempt';
            $reattempt = 0;
            $nextbuttontext = get_string('continue', constants::M_COMPONENT);
        } else {
            $nextaction = 'none';
            $reattempt = 0;
            $nextbuttontext = get_string('continue', constants::M_COMPONENT);
        }

        // config
        $config = get_config('mod_wordcards');
        $token = utils::fetch_token($config->apiuser, $config->apisecret);
        $journeymode = $mod->get_mod()->journeymode; // get_config(constants::M_COMPONENT, 'journeymode');

        // video examples (or not)
        if ($mod->get_mod()->videoexamples) {
            // fetch the lang name and accent (if any) for youglish
            $youglish = utils::get_youglish_config($mod->get_mod()->ttslanguage);
        } else {
            $youglish = false;
        }

        $data = [
            'uniqid' => \html_writer::random_id('wordcards'),
            'canmanage' => $mod->can_manage(),
            'canattempt' => $canattempt,
            'attemptcount' => $attemptcount,
            'maxattempts' => $maxattempts,
            'nextaction' => $nextaction,
            'nextbuttontext' => $nextbuttontext,
            'isreattempt' => $isreattempt,
            'str_definition' => get_string('definition', 'mod_wordcards'),
            'definitions' => array_values($definitions),
            'youglish' => $youglish,
            'gotit' => get_string('gotit', 'mod_wordcards'),
            'loading' => get_string('loading', 'mod_wordcards'),
            'loadingurl' => $this->image_url('i/loading_small')->out(true),
            'markasseen' => get_string('markasseen', 'mod_wordcards'),
            'modid' => $mod->get_id(),
            'nexturl' => (new \moodle_url('/mod/wordcards/activity.php', [
                'id' => $mod->get_cmid(),
                'nextstep' => \mod_wordcards_module::STATE_STEP1,
                'reattempt' => $reattempt,
                'embed' => $embed,
            ]))->out(true),
            'noteaboutseenforteachers' => get_string('noteaboutseenforteachers', 'mod_wordcards'),
            'notseenurl' => $this->image_url('not-seen', 'mod_wordcards')->out(true),
            'definition_grid' => $this->image_url('grid', 'mod_wordcards')->out(true),
            'definition_flashcards' => $this->image_url('flashcards', 'mod_wordcards')->out(true),
            'seenurl' => $this->image_url('seen', 'mod_wordcards')->out(true),
            'str_term' => get_string('term', 'mod_wordcards'),
            'termnotseen' => get_string('termnotseen', 'mod_wordcards'),
            'termseen' => get_string('termseen', 'mod_wordcards'),
            'token' => $token,
            'region' => $config->awsregion,
            'owner' => hash('md5', $USER->username),
            'cloudpoodllurl' => utils::get_cloud_poodll_server(),
            'cmid' => $mod->get_cmid(),
            'freemodeavailable' => $journeymode == constants::MODE_FREE || ($journeymode == constants::MODE_STEPSTHENFREE && $attemptcount > 0),
            'stepsmodeavailable' => $journeymode == constants::MODE_STEPS || $journeymode == constants::MODE_STEPSTHENFREE,
        ];

        $data['optshtml'] = \html_writer::tag('input', '', ['id' => $data['uniqid'], 'type' => 'hidden', 'value' => json_encode($data)]);
        $jsdata = ['widgetid' => $data['uniqid']];
        $this->page->requires->js_call_amd("mod_wordcards/definitions", 'init', [$jsdata]);

        // Heading and intro paras (these are after opthtml for JS as we dont want to include them there).
        $data['introheading'] = get_string('tabdefinitions', 'mod_wordcards');
        $stringmanager = get_string_manager();
        $data['introstrings'] = [];
        for ($x = 1; $x <= 10; $x++) {
            $stringkey = 'startintropara' . $x;
            if ($stringmanager->string_exists($stringkey, 'mod_wordcards')) {
                $data['introstrings'][] = get_string($stringkey, 'mod_wordcards');
            } else {
                break;
            }
        }
        return $data;
    }

    // Use page layout to reconstruct embed flag so we can generate links that maintain it.
    public function get_embed_flag()
    {
        switch ($this->page->pagelayout) {
            case 'popup':
                $embed = 2;
                break;
            case 'embedded':
                $embed = 1;
                break;
            default:
                $embed = 0;
        }
        return $embed;
    }

    public function cancel_attempt_button($mod)
    {

        // teachers can not attempt, so they cant quite attempts either
        if ($mod->can_manage() || $mod->can_viewreports()) {
            return "";
        }

        $data = [
            'modid' => $mod->get_id(),
            'cancelurl' => (new \moodle_url('/mod/wordcards/activity.php', [
                'id' => $mod->get_cmid(),
                'cancelattempt' => 1,
                'embed' => $this->get_embed_flag(),
            ]))->out(true),
        ];
        $this->page->requires->js_call_amd("mod_wordcards/cancel_attempt_button", 'init', [$data]);
        return $this->render_from_template('mod_wordcards/cancel_attempt_button', $data);
    }

    public function no_definitions_yet($mod)
    {
        $displaytext = $this->output->box_start();
        $displaytext .= $this->output->heading(get_string('nodefinitions', constants::M_COMPONENT), 3, 'main');
        $showaddwordlinks = $mod->can_manage();
        if ($showaddwordlinks) {
            $displaytext .= \html_writer::div(get_string('letsaddwords', constants::M_COMPONENT), '', []);
            $displaytext .= $this->output->single_button(new \moodle_url(
                constants::M_URL . '/managewords.php',
                ['id' => $mod->get_cmid()]
            ), get_string('addwords', constants::M_COMPONENT));
        }
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, '');
        return $ret;
    }

    private function make_json_string($definitions, $mod)
    {

        $defs = [];
        foreach ($definitions as $definition) {
            $def = new \stdClass();
            $def->image = $definition->image;
            $def->audio = $definition->audio;
            $def->alternates = $definition->alternates;
            $def->ttsvoice = $definition->ttsvoice;
            $def->id = $definition->id;
            $def->term = $definition->term;
            $def->model_sentence = $definition->model_sentence;
            $def->model_sentence_audio = $definition->model_sentence_audio;
            $def->definition = $definition->definition;
            if (isset($definition->learned)) {
                $def->learned = $definition->learned;
            }
            if (isset($definition->learned_progress)) {
                $def->learnedprogress = $definition->learned_progress;
            }
            if ($mod->get_mod()->showimageflip) {
                $def->showimageflip = true;
            }
            // which face to tag as front and which as back
            if ($mod->get_mod()->frontfaceflip == constants::M_FRONTFACEFLIP_DEF) {
                $def->frontfacedef = true;
            }
            $defs[] = $def;
        }
        $defsobject = new \stdClass();
        $defsobject->terms = $defs;
        return json_encode($defsobject);
    }


    public function a4e_page(\mod_wordcards_module $mod, int $practicetype, array $definitions, int $currentmode, $currentstep = '')
    {
        global $USER, $PAGE, $OUTPUT;

        // Config.
        $config = get_config('mod_wordcards');

        // Get state.
        list($state) = $mod->get_state();

        // make sure each definition has a voice
        foreach ($definitions as $def) {
            if ($def->ttsvoice == 'Auto' || $def->ttsvoice == '') {
                $def->ttsvoice = utils::fetch_auto_voice($mod->get_mod()->ttslanguage);
            }
        }

        $widgetid = \html_writer::random_id();
        $jsonstring = $this->make_json_string($definitions, $mod);
        $optshtml = \html_writer::tag('input', '', ['id' => $widgetid, 'type' => 'hidden', 'value' => $jsonstring]);

        if ($currentstep) {
            $nextstep = $mod->get_next_step($currentstep);
            $nexturl = (new \moodle_url(
                '/mod/wordcards/activity.php',
                [
                    'id' => $mod->get_cmid(),
                    'oldstep' => $currentstep,
                    'nextstep' => $nextstep,
                    'embed' => $this->get_embed_flag()
                ]
            ))->out(true);
        } else {
            // In Freemode we will not have a next or current step, so we pass an empty next URL to JS.
            $nexturl = '';
        }

        $token = utils::fetch_token($config->apiuser, $config->apisecret);

        $opts = [
            'widgetid' => $widgetid,
            'ttslanguage' => $mod->get_mod()->ttslanguage,
            'dryRun' => $mod->can_manage() && ($currentmode == constants::CURRENTMODE_STEPS),
            'nexturl' => $nexturl,
            'region' => $config->awsregion,
            'token' => $token,
            'owner' => hash('md5', $USER->username),
            'cloudpoodllurl' => utils::get_cloud_poodll_server(),
            'modid' => $mod->get_id(),
            'isfreemode' => ($currentmode == constants::CURRENTMODE_FREE),
        ];

        $data = [];
        switch ($practicetype) {
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV:
                $opts['msoptions'] = $mod->get_mod()->msoptions;
                $this->page->requires->js_call_amd("mod_wordcards/matchselect", 'init', [$opts]);
                $activityhtml = $this->render_from_template('mod_wordcards/matchselect_page', $data);
                break;
            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE_REV:
                $this->page->requires->js_call_amd("mod_wordcards/matchtype", 'init', [$opts]);
                $activityhtml = $this->render_from_template('mod_wordcards/matchtype_page', $data);
                break;
            case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE:
            case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE_REV:
                $opts['lcoptions'] = $mod->get_mod()->lcoptions;
                $this->page->requires->js_call_amd("mod_wordcards/listenchoose", 'init', [$opts]);
                $activityhtml = $this->render_from_template('mod_wordcards/listenchoose_page', $data);
                break;
            case \mod_wordcards_module::PRACTICETYPE_SPACEGAME:
            case \mod_wordcards_module::PRACTICETYPE_SPACEGAME_REV:
                $opts['sgoptions'] = $mod->get_mod()->sgoptions;
                $this->page->requires->js_call_amd("mod_wordcards/spacegame", 'init', [$opts]);
                $activityhtml = $this->render_from_template('mod_wordcards/spacegame_page', $data);
                break;

            case \mod_wordcards_module::PRACTICETYPE_WORDPREVIEW:
            case \mod_wordcards_module::PRACTICETYPE_WORDPREVIEW_REV:
                // $opts['wpoptions'] = $mod->get_mod()->wpoptions;
                $this->page->requires->js_call_amd("mod_wordcards/wordpreview", 'init', [$opts]);
                $activityhtml = $this->render_from_template('mod_wordcards/wordpreview_page', $data);
                break;

            case \mod_wordcards_module::PRACTICETYPE_DICTATION:
            case \mod_wordcards_module::PRACTICETYPE_DICTATION_REV:
            default:
                $this->page->requires->js_call_amd("mod_wordcards/dictation", 'init', [$opts]);
                $activityhtml = $this->render_from_template('mod_wordcards/dictation_page', $data);
        }
        return $optshtml . $activityhtml;
    }

    public function finish_page(\mod_wordcards_module $mod)
    {
        global $CFG;

        $data = [
            'canmanage' => $mod->can_manage(),
            'modid' => $mod->get_id(),
            'freemodeurl' => $CFG->wwwroot . '/mod/wordcards/freemode.php?id=' . $mod->get_cmid() . '&embed=' . $this->get_embed_flag(),
            'canfreemode' => $mod->can_free_mode(),
        ];

        if ($this->get_embed_flag() == 0) {
            $data['courseurl'] = $CFG->wwwroot . '/course/view.php?id=' . $this->page->course->id . '#section-' . $mod->get_cm()->sectionnum;
        }

        // attempt info
        $canattempt = $mod->can_attempt();
        if ($canattempt) {
            $data['reattempturl'] = $CFG->wwwroot . '/mod/wordcards/view.php?id=' . $mod->get_cmid() . '&embed=' . $this->get_embed_flag();
        }

        // if we have a latest attempt, we need STARS!!!
        // teachers attempts are not saved, so they have no score when they get to the finished page
        $latestattempt = $mod->get_latest_attempt();
        if ($latestattempt) {

            // final score
            $data['hasscore'] = true;
            $data['total'] = $latestattempt->totalgrade;

            // total rating
            [$totalyellowstars, $totalgraystars] = utils::get_stars($latestattempt->totalgrade);
            $data['totalyellowstars'] = $totalyellowstars;
            $data['totalgraystars'] = $totalgraystars;

            // each steps rating
            $ratingitems = [];
            for ($x = 1; $x < 6; $x++) {
                $practicetype = $mod->get_mod()->{'step' . $x . 'practicetype'};
                if ((int) $practicetype !== \mod_wordcards_module::PRACTICETYPE_NONE) {
                    $ratingitem = new \stdClass();
                    $ratingitem->grade = $latestattempt->{"grade" . $x};
                    $ratingitem->icon = utils::fetch_activity_tabicon($practicetype);
                    $ratingitem->title = utils::get_practicetype_label($practicetype);
                    [$yellowstars, $graystars] = utils::get_stars($ratingitem->grade);
                    $ratingitem->yellowstars = $yellowstars;
                    $ratingitem->graystars = $graystars;
                    $ratingitems[] = $ratingitem;
                }
            }
            $data['ratingitems'] = $ratingitems;
        }

        return $this->render_from_template('mod_wordcards/finish_page', $data);
    }

    public function push_recorder()
    {
        $data = [];
        return $this->render_from_template('mod_wordcards/pushrecorder', $data);
    }

    /**
     * Initialises the game and returns its HTML code
     *
     * @param stdClass $quizgame The quizgame to be added
     * @param context $context The context
     * @return string The HTML code of the game
     */
    public function spacegame_page(\mod_wordcards_module $mod, array $definitions, int $currentmode, $currentstep = '')
    {
        global $USER;

        // config
        $config = get_config('mod_wordcards');

        // get state
        list($state) = $mod->get_state();

        $widgetid = \html_writer::random_id();
        $jsonstring = $this->make_json_string($definitions, $mod);
        $optshtml = \html_writer::tag('input', '', ['id' => $widgetid, 'type' => 'hidden', 'value' => $jsonstring]);

        if ($currentstep) {
            $nextstep = $mod->get_next_step($currentstep);
            $nexturl = (new \moodle_url('/mod/wordcards/activity.php', ['id' => $mod->get_cmid(), 'oldstep' => $currentstep, 'nextstep' => $nextstep]))->out(true);
        } else {
            // In Freemode we will not have a next or current step, so we pass an empty next URL to JS.
            $nexturl = '';
        }

        $token = utils::fetch_token($config->apiuser, $config->apisecret);

        $opts = [];
        $opts['widgetid'] = $widgetid;
        $opts['dryRun'] = $mod->can_manage() && ($currentmode == constants::CURRENTMODE_STEPS);
        $opts['nexturl'] = $nexturl;
        $opts['token'] = $token;
        $opts['owner'] = hash('md5', $USER->username);
        $opts['cloudpoodllurl'] = utils::get_cloud_poodll_server();
        $opts['appid'] = constants::M_COMPONENT;
        $opts['modid'] = $mod->get_id();
        $opts['isfreemode'] = ($currentmode == constants::CURRENTMODE_FREE);


        $this->page->requires->strings_for_js([
            'score',
            'emptyquiz',
            'endofgame',
            'spacetostart',
            'shootthepairs',
        ], 'mod_wordcards');

        $opts['sgoptions'] = $mod->get_mod()->sgoptions;
        $this->page->requires->js_call_amd('mod_wordcards/spacegame', 'init', [$opts]);
        $spacegamehtml = $this->render_from_template('mod_wordcards/spacegame_page', []);
        return $optshtml . $spacegamehtml;
    }




    public function speechcards_page(\mod_wordcards_module $mod, array $definitions, int $currentmode, $currentstep = '')
    {
        global $CFG, $USER;

        // get state
        list($state) = $mod->get_state();

        // fitst confirm we have the cloud poodll token and can show the cards
        $apiuser = get_config(constants::M_COMPONENT, 'apiuser');
        $apisecret = get_config(constants::M_COMPONENT, 'apisecret');
        $region = get_config(constants::M_COMPONENT, 'awsregion');

        // check user has entered api credentials
        if (empty($apiuser) || empty($apisecret)) {
            $errormessage = get_string(
                'nocredentials',
                constants::M_COMPONENT,
                $CFG->wwwroot . constants::M_PLUGINSETTINGS
            );
            return ($this->show_problembox($errormessage));
        } else {
            $token = utils::fetch_token($apiuser, $apisecret);

            // check token authenticated and no errors in it
            $errormessage = utils::fetch_token_error($token);
            if (!empty($errormessage)) {
                return ($this->show_problembox($errormessage));
            }
        }

        // ok we now have a token and can continue to set up the cards
        $widgetid = \html_writer::random_id();

        // next url
        $nextstep = $mod->get_next_step($currentstep);
        $nexturl = (new \moodle_url('/mod/wordcards/activity.php', ['id' => $mod->get_cmid(), 'oldstep' => $currentstep, 'nextstep' => $nextstep]))->out(true);

        // make sure each definition has a voice
        foreach ($definitions as $def) {
            if ($def->ttsvoice == 'Auto' || $def->ttsvoice == '') {
                $def->ttsvoice = utils::fetch_auto_voice($mod->get_mod()->ttslanguage);
            }
        }

        $jsonstring = $this->make_json_string($definitions, $mod);
        $optshtml = \html_writer::tag('input', '', ['id' => $widgetid, 'type' => 'hidden', 'value' => $jsonstring]);

        $opts = [];
        $opts['widgetid'] = $widgetid;
        $opts['dryRun'] = $mod->can_manage() && ($currentmode == constants::CURRENTMODE_STEPS);
        $opts['nexturl'] = $nexturl;
        $opts['language'] = $mod->get_mod()->ttslanguage;
        $opts['region'] = $region;
        $opts['token'] = $token;
        $opts['parent'] = $CFG->wwwroot;
        $opts['owner'] = hash('md5', $USER->username);
        $opts['cloudpoodllurl'] = utils::get_cloud_poodll_server();
        $opts['appid'] = constants::M_COMPONENT;
        $opts['modid'] = $mod->get_id();
        $opts['expiretime'] = 300;// max expire time is 300 seconds
        $opts['useanimatecss'] = get_config(constants::M_COMPONENT, 'animations') == constants::M_ANIM_FANCY;
        $opts['cardface'] = $mod->get_mod()->scoptions == constants::M_WC_TERM_AS_READABLE ? 'term' : 'model_sentence';

        if ($mod->get_mod()->transcriber == constants::TRANSCRIBER_POODLL) {
            // this will force browser recognition to use Poodll (not chrome or other browser speech)
            $opts['stt_guided'] = true;
        } else {
            $opts['stt_guided'] = false;
        }

        $this->page->requires->js_call_amd("mod_wordcards/speechcards", 'init', [$opts]);

        // are we going to force streaning transcription from AWS only if its android
        $hints = new \stdClass();
        // $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
        // if(stripos($ua,'android') !== false) {
        // $hints->streamingtranscriber = 'aws';
        // }
        $stringhints = base64_encode(json_encode($hints));

        $data = [];
        $data['cloudpoodlltoken'] = $token;
        $data['language'] = $mod->get_mod()->ttslanguage;
        $data['wwwroot'] = $CFG->wwwroot;
        $data['region'] = $region;
        $data['hints'] = $stringhints;
        $data['owner'] = hash('md5', $USER->username);
        $opts['cloudpoodllurl'] = utils::get_cloud_poodll_server();

        // TT Recorder ---------------
        $data['waveheight'] = 75;
        $data['maxtime'] = 15000;
        $data['data-id'] = 'wordcards-speechcards_pushrecorder';
        // passagehash if not empty will be region|hash eg tokyo|2353531453415134545
        // but we only send the hash up so we strip the region
        $data['passagehash'] = "";
        if (!empty($mod->get_mod()->passagehash)) {
            $hashbits = explode('|', $mod->get_mod()->passagehash);
            if (count($hashbits) == 2) {
                $data['passagehash'] = $hashbits[1];
            }
        }
        switch ($region) {

            case 'useast1':
                $data['asrurl'] = 'https://useast.ls.poodll.com/transcribe';
                break;

            case 'ningxia':
                $data['asrurl'] = 'https://ningxia.ls.poodll.cn/transcribe';
                break;

            default:
                $data['asrurl'] = 'https://' . $region . '.ls.poodll.com/transcribe';

        }

        $speechcards = $this->render_from_template('mod_wordcards/speechcards_page', $data);
        return $optshtml . $speechcards;

    }

    public function scatter_page(\mod_wordcards_module $mod, $wordpool, $currentstep)
    {
        list($state) = $mod->get_state();

        $nextstep = $mod->get_next_step($currentstep);
        $nexturl = (new \moodle_url('/mod/wordcards/activity.php', ['id' => $mod->get_cmid(), 'oldstep' => $currentstep, 'nextstep' => $nextstep]))->out(true);

        // if we are in review state, we use different words and the next page is a finish page
        if ($wordpool == \mod_wordcards_module::WORDPOOL_REVIEW) {
            $definitions = $mod->get_review_terms($mod->fetch_step_termcount($currentstep));
        } else {
            $definitions = $mod->get_learn_terms($mod->fetch_step_termcount($currentstep));
        }

        // make sure each definition has a voice
        foreach ($definitions as $def) {
            if ($def->ttsvoice == 'Auto' || $def->ttsvoice == '') {
                $def->ttsvoice = utils::fetch_auto_voice($mod->get_mod()->ttslanguage);
            }
        }

        $data = [
            'canmanage' => $mod->can_manage(),
            'continue' => get_string('continue'),
            'congrats' => get_string('congrats', 'mod_wordcards'),
            'definitionsjson' => json_encode(array_values($definitions)),
            'finishscatterin' => get_string('finishscatterin', 'mod_wordcards'),
            'modid' => $mod->get_id(),
            'isglobalcompleted' => $state == \mod_wordcards_module::STATE_END,
            'hascontinue' => $state != \mod_wordcards_module::STATE_END,
            'nexturl' => $nexturl,
            'isglobalscatter' => true,
        ];

        return $this->render_from_template('mod_wordcards/scatter_page', $data);
    }


    public function navigation(\mod_wordcards_module $mod, $currentstate, $navdisabled = false)
    {
        $embed = $this->get_embed_flag();
        $tabtree = \mod_wordcards_helper::get_tabs($mod, $currentstate, $embed);
        if (($mod->can_manage() || $mod->can_viewreports()) && $embed !== 2) {
            // Teachers see the tabs, as normal tabs.
            return $this->render($tabtree);
        }

        $seencurrent = false;
        $step = 1;
        $tabs = array_map(function ($tab) use ($seencurrent, $currentstate, &$step, $tabtree, $navdisabled) {
            $current = $tab->id == $currentstate;
            $seencurrent = $current || $seencurrent;
            $icon = $tab->title;
            $tab->title = '';

            return [
                'id' => $tab->id,
                'url' => $tab->link,
                'text' => $tab->text,
                'title' => '',
                'icon' => $icon,
                'current' => $tab->selected,
                'inactive' => $tab->inactive || $navdisabled,
                'last' => $step == count($tabtree->subtree),
                'step' => $step++,
            ];
        }, $tabtree->subtree);

        $data = [
            'tabs' => $tabs,
            'cmid' => $mod->get_cmid(),
        ];
        return $this->render_from_template('mod_wordcards/student_navigation', $data);
    }

    public function language_chooser($activitydefinitionslanguage)
    {
        global $CFG;

        // get selected definitions language
        $selected = $activitydefinitionslanguage;
        $userprefdeflanguage = get_user_preferences('wordcards_deflang');
        if (!empty($userprefdeflanguage)) {
            $selected = $userprefdeflanguage;
        }

        // Get list of langs..
        // We pass in the selected lang but since we build the dropdown list differently here,.
        // We actually set selected in html_writer below.
        $alldeflangs = utils::get_rcdic_langs($selected, false);
        $options = [];
        foreach ($alldeflangs as $deflang) {
            $options[$deflang['code']] = $deflang['name'];
        }
        // prepare data for js and html in mustache template
        $data = [];
        $data['dropdownlist'] = \html_writer::select($options, 'mydefs_lang', $selected);
        $data['activitydefault'] = $activitydefinitionslanguage;
        return $this->render_from_template('mod_wordcards/deflang_chooser', $data);
    }

    /**
     * Return HTML to embed a minilesson
     */
    public function embed_nativelangchooser($cmid, $token)
    {
        global $DB;
        $currentlang = get_user_preferences('wordcards_deflang');
        return $this->language_chooser($currentlang);
    }

    public function embed_usercourseprogress($courseid)
    {
        global $DB, $USER;

        //get all wordcards in course
        $wordcardsids = $DB->get_fieldset_select(constants::M_TABLE, 'id', 'course = ?', array($courseid));
        list($wordcardswhere, $allparams) = $DB->get_in_or_equal($wordcardsids);

        //get total terms
        $countsql = "SELECT COUNT(t.id) FROM {wordcards_terms} t
                     INNER JOIN {wordcards} m
                     ON t.modid = m.id
                     WHERE m.course = ? 
                     AND t.deleted = 0";
        $totalterms = $DB->count_records_sql($countsql, [$courseid]);

        $allsql = "SELECT COUNT((CASE WHEN a.successcount >=  m.learnpoint  THEN 1 END)) as termslearned, SUM(a.selfclaim) as selfclaimed, $totalterms as totalterms 
                  FROM {wordcards_associations} a
                  INNER JOIN {wordcards_terms} t
                  INNER JOIN {wordcards} m
                    ON a.termid = t.id
                    AND t.modid = m.id     
                    WHERE t.modid $wordcardswhere
                    AND t.deleted = 0
                    AND a.userid = ?";

        $allparams = array_merge($allparams, [$USER->id]);
        $alldata = $DB->get_record_sql($allsql, $allparams);
        if ($alldata) {
            $data = [
                'termslearned' => $alldata->termslearned - $alldata->selfclaimed,
                'termstolearn' => $alldata->totalterms - $alldata->termslearned,
                'totalterms' => $alldata->totalterms - $alldata->selfclaimed,
            ];
            return $this->render_from_template('mod_wordcards/user_course_progress', $data);
        } else {
            // No data found, return empty string or a message.
            return '';
        }
    }

    /**
     * Return HTML to display message about problem
     */
    public function show_problembox($msg)
    {
        $output = '';
        $output .= $this->output->box_start(constants::M_COMPONENT . '_problembox');
        $output .= $this->notification($msg, 'warning');
        $output .= $this->output->box_end();
        return $output;
    }

    /*
     * Show open and close dates to the activity
     */
    public function show_open_close_dates($moduleinstance)
    {
        $tdata = [];
        if ($moduleinstance->viewstart > 0) {
            $tdata['opendate'] = $moduleinstance->viewstart;
        }
        if ($moduleinstance->viewend > 0) {
            $tdata['closedate'] = $moduleinstance->viewend;
        }
        $ret = $this->output->render_from_template(constants::M_COMPONENT . '/openclosedates', $tdata);
        return $ret;
    }


    /**
     * Push Menu
     */
    public function push_buttons_menu($cm)
    {
        $templateitems = [];
        $pushthings = [
            'maxattempts',
            'transcriber',
            'learnpoint',
            'showimageflip',
            'frontfaceflip',
            'showlangchooser',
            'videoexamples',
            'journeymode',
            'stepsmodeoptions',
            'freemodeoptions',
            'lcoptions',
            'msoptions',
            'sgoptions',
        ];

        foreach ($pushthings as $pushthing) {
            switch ($pushthing) {
                case 'transcriber':
                    $action = constants::M_PUSH_TRANSCRIBER;
                    break;
                case 'showimageflip':
                    $action = constants::M_PUSH_SHOWIMAGEFLIP;
                    break;
                case 'showlangchooser':
                    $action = constants::M_PUSH_SHOWLANGCHOOSER;
                    break;
                case 'showvideoexamples':
                    $action = constants::M_PUSH_VIDEOEXAMPLES;
                    break;
                case 'sgoptions':
                    $action = constants::M_PUSH_SGOPTIONS;
                    break;
                case 'stepsmodeoptions':
                    $action = constants::M_PUSH_STEPSMODEOPTIONS;
                    break;
                case 'maxattempts':
                    $action = constants::M_PUSH_MAXATTEMPTS;
                    break;
                case 'freemodeoptions':
                    $action = constants::M_PUSH_FREEMODEOPTIONS;
                    break;
                case 'frontfaceflip':
                    $action = constants::M_PUSH_FRONTFACEFLIP;
                    break;
                case 'journeymode':
                    $action = constants::M_PUSH_JOURNEYMODE;
                    break;
                case 'lcoptions':
                    $action = constants::M_PUSH_LCOPTIONS;
                    break;
                case 'learnpoint':
                    $action = constants::M_PUSH_LEARNPOINT;
                    break;
                case 'msoptions':
                    $action = constants::M_PUSH_MSOPTIONS;
                    break;

            }
            $templateitems[] = [
                'title' => get_string($pushthing, constants::M_COMPONENT),
                'description' => get_string($pushthing . '_details', constants::M_COMPONENT),
                'content' => $this->output->single_button(new \moodle_url(
                    constants::M_URL . '/push.php',
                    ['id' => $cm->id, 'action' => $action]
                ), get_string($pushthing, constants::M_COMPONENT))
            ];
        }

        // Generate and return menu
        $ret = $this->output->render_from_template(constants::M_COMPONENT . '/manybuttonsmenu', ['items' => $templateitems]);

        return $ret;

    }

    /**
     * Word Wizard
     */
    public function word_wizard($mod, $cm)
    {
        $langterm = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);
        $includeother = false;
        $langdefs = utils::get_rcdic_langs($mod->get_mod()->deflanguage, $includeother);

        $data = [
            'modid' => $mod->get_mod()->id,
            'cmid' => $cm->id,
            'langterm' => $langterm,
            'langdefs' => $langdefs,
        ];
        return $this->render_from_template('mod_wordcards/word_wizard', $data);
    }

    /**
     * Depending on wordpool etc, get page title.
     * @param int $practicetype
     * @param int $wordpool
     * @return string
     * @throws \coding_exception
     */
    public function page_heading(int $practicetype, int $wordpool): string
    {
        // First practice type.
        if ($practicetype !== \mod_wordcards_module::PRACTICETYPE_NONE) {
            $practicetypeoptions = utils::get_practicetype_options();
            $pagetitle = isset($practicetypeoptions[$practicetype]) ? $practicetypeoptions[$practicetype] : '';
        } else {
            $pagetitle = get_string('introduction', constants::M_COMPONENT);
        }
        // Then wordpool.
        $wordpoolstring = $this->get_wordpool_string($wordpool);
        if ($wordpoolstring) {
            $pagetitle = $pagetitle . ' (' . $wordpoolstring . ')';
        }
        return $pagetitle;
    }

    public function get_wordpool_string(int $wordpoolid)
    {
        $wordpoolstringkeys = [
            \mod_wordcards_module::WORDPOOL_LEARN => 'learnactivity',
            \mod_wordcards_module::WORDPOOL_REVIEW => 'seenwords',
            \mod_wordcards_module::WORDPOOL_MY_WORDS => 'mywords',
        ];
        return isset($wordpoolstringkeys[$wordpoolid]) ? get_string($wordpoolstringkeys[$wordpoolid], 'mod_wordcards') : '';
    }
}
