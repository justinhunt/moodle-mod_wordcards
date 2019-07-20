<?php
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
class mod_wordcards_renderer extends plugin_renderer_base {

    public function definitions_page(mod_wordcards_module $mod) {
        global $PAGE, $OUTPUT;

        $definitions = $mod->get_terms();
        if (empty($definitions)) {
            return $OUTPUT->notification(get_string('nodefinitions', 'mod_wordcards'));
        }

        // Get whe the student has seen.
        $seen = $mod->get_terms_seen();
        foreach ($seen as $s) {
            if (!isset($definitions[$s->termid])) {
                // Shouldn't happen.
                continue;
            }
            $definitions[$s->termid]->seen = true;
        }

        $data = [
            'canmanage' => $mod->can_manage(),
            'str_definition' => get_string('definition', 'mod_wordcards'),
            'definitions' => array_values($definitions),
            'gotit' => get_string('gotit', 'mod_wordcards'),
            'loading' => get_string('loading', 'mod_wordcards'),
            'loadingurl' => $this->image_url('i/loading_small')->out(true),
            'markasseen' => get_string('markasseen', 'mod_wordcards'),
            'modid' => $mod->get_id(),
            'mustseealltocontinue' => get_string('mustseealltocontinue', 'mod_wordcards'),
            'nexturl' => (new moodle_url('/mod/wordcards/local.php', ['id' => $mod->get_cmid()]))->out(true),
            'noteaboutseenforteachers' => get_string('noteaboutseenforteachers', 'mod_wordcards'),
            'notseenurl' => $this->image_url('not-seen', 'mod_wordcards')->out(true),
            'seenall' => count($definitions) == count($seen),
            'seenurl' => $this->image_url('seen', 'mod_wordcards')->out(true),
            'str_term' => get_string('term', 'mod_wordcards'),
            'termnotseen' => get_string('termnotseen', 'mod_wordcards'),
            'termseen' => get_string('termseen', 'mod_wordcards'),
        ];

        return $this->render_from_template('mod_wordcards/definitions_page', $data);
    }

    private function fetch_data_json_feelings(){
        return   '{
            "id": 167438802,
    "url": "https://quizlet.com/167438802/animals-flash-cards/",
    "title": "Animals",
    "created_by": "praine",
    "term_count": 38,
    "created_date": 1478830914,
    "modified_date": 1527157617,
    "published_date": 1478830993,
    "has_images": false,
    "subjects": [],
    "visibility": "public",
    "editable": "only_me",
    "has_access": true,
    "can_edit": false,
    "description": "",
    "lang_terms": "en",
    "lang_definitions": "photo",
    "password_use": 0,
    "password_edit": 0,
    "access_type": 2,
    "creator_id": 6927709,
    "creator": {
            "username": "praine",
        "account_type": "teacher",
        "profile_image": "https://up.quizlet.com/44hgd-YM4VX-256s.jpg",
        "id": 6927709
    },
    "class_ids": [
            5712221
        ],
    "terms": [
        {
            "id": 5412283994,
            "term": "happy",
            "definition": "幸せ",
            "image": null,
            "rank": 0
        },
        {
            "id": 5412284059,
            "term": "sad",
            "definition": "悲しい",
            "image": null,
            "rank": 37
        },
            {
            "id": 5412283994,
            "term": "jealous",
            "definition": "羨ましい",
            "image": null,
            "rank": 0
        },
        {
            "id": 5412284059,
            "term": "joyful",
            "definition": "すごい幸せ",
            "image": null,
            "rank": 37
        }
   
    ]
}';
    }

    private function fetch_data_json_animals() {
      return   '{
            "id": 167438802,
    "url": "https://quizlet.com/167438802/animals-flash-cards/",
    "title": "Animals",
    "created_by": "praine",
    "term_count": 38,
    "created_date": 1478830914,
    "modified_date": 1527157617,
    "published_date": 1478830993,
    "has_images": true,
    "subjects": [],
    "visibility": "public",
    "editable": "only_me",
    "has_access": true,
    "can_edit": false,
    "description": "",
    "lang_terms": "en",
    "lang_definitions": "photo",
    "password_use": 0,
    "password_edit": 0,
    "access_type": 2,
    "creator_id": 6927709,
    "creator": {
            "username": "praine",
        "account_type": "teacher",
        "profile_image": "https://up.quizlet.com/44hgd-YM4VX-256s.jpg",
        "id": 6927709
    },
    "class_ids": [
            5712221
        ],
    "terms": [
        {
            "id": 5412283994,
            "term": "camel",
            "definition": "",
            "image": {
            "url": "https://o.quizlet.com/M4R8lUv7vCFwXvYJ5w.j3g_m.jpg",
                "width": 240,
                "height": 160
            },
            "rank": 0
        },
        {
            "id": 5412283996,
            "term": "hedgehog",
            "definition": "",
            "image": {
            "url": "https://o.quizlet.com/-xvgA4dGE1qFOumpXNMyKg_m.jpg",
                "width": 240,
                "height": 160
            },
            "rank": 1
        }
    ]
}';

    }

    private function make_json_string($definitions){

        $defs = array();
        foreach ($definitions as $definition){
            $def = new stdClass();
            $def->image=null;
            $def->id=$definition->id;
            $def->term =$definition->term;
            $def->definition =$definition->definition;
            $defs[]=$def;
        }
        $defs_object = new stdClass();
        $defs_object->terms = $defs;
        return json_encode($defs_object);
    }


    public function local_a4e_page(mod_wordcards_module $mod) {
        global $PAGE, $OUTPUT;


        $widgetid = \html_writer::random_id();
        $definitions = $mod->get_local_terms();
        $jsonstring=$this->make_json_string($definitions);
        //$jsonstring = $this->fetch_data_json_feelings();
        $opts_html = \html_writer::tag('input', '', array('id' => $widgetid, 'type' => 'hidden', 'value' => $jsonstring));

        //need to check cards_page.mustache but i think we do not need 'hascontinue' feature
        ///$hascontinue = true;

        $completeafterlocal = $mod->completeafterlocal();
        $nexturl = empty($completeafterlocal) ? (new moodle_url('/mod/wordcards/global.php', ['id' => $mod->get_cmid()]))->out(true)
            : (new moodle_url('/mod/wordcards/finish.php', ['id' => $mod->get_cmid(), 'sesskey' => sesskey()]))->out(true);
        $opts=array('widgetid'=>$widgetid,'dryRun'=> $mod->can_manage(),'nexturl'=>$nexturl);
        switch($mod->get_localpracticetype()){
            case mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
                $this->page->requires->js_call_amd("mod_wordcards/matchselect", 'init', array($opts));
                break;
            case mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
            case mod_wordcards_module::PRACTICETYPE_DICTATION:
            default:
                $this->page->requires->js_call_amd("mod_wordcards/matchtype", 'init', array($opts));
        }

        $data = [];
        $matching = $this->render_from_template('mod_wordcards/matching_page', $data);
        return $opts_html . $matching;
    }

    public function global_a4e_page(mod_wordcards_module $mod) {
        global $PAGE, $OUTPUT;


        $widgetid = \html_writer::random_id();
        $definitions = $mod->get_global_terms();
        $jsonstring=$this->make_json_string($definitions);
        //$jsonstring = $this->fetch_data_json_feelings();
        $opts_html = \html_writer::tag('input', '', array('id' => $widgetid, 'type' => 'hidden', 'value' => $jsonstring));

        //need to check cards_page.mustache but i think we do not need 'hascontinue' feature
        //list($state) = $mod->get_state();
       // $hascontinue = $state != mod_wordcards_module::STATE_END;

        $nexturl = (new moodle_url('/mod/wordcards/finish.php',
            ['id' => $mod->get_cmid(), 'sesskey' => sesskey()]))->out(true);

        $opts=array('widgetid'=>$widgetid,'dryRun'=> $mod->can_manage(),'nexturl'=>$nexturl);
        switch($mod->get_globalpracticetype()){
            case mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
                $this->page->requires->js_call_amd("mod_wordcards/matchselect", 'init', array($opts));
                break;
            case mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
            case mod_wordcards_module::PRACTICETYPE_DICTATION:
            default:
                $this->page->requires->js_call_amd("mod_wordcards/matchtype", 'init', array($opts));
        }

        $data = [];
        $matching = $this->render_from_template('mod_wordcards/matching_page', $data);
        return $opts_html . $matching;
    }

    public function finish_page(mod_wordcards_module $mod, $globalscattertime = 0, $localscattertime = 0) {
        if (!empty($globalscattertime)) {
            $scattertime = $globalscattertime;
        } else {
            $scattertime = $localscattertime;
        }
        $scattertimemsg = $mod->get_finishedscattermsg();
        $scattertimemsg = str_replace('[[time]]', gmdate("i:s:00", $scattertime), $scattertimemsg);

        $data = [
            'canmanage' => $mod->can_manage(),
            'finishtext' => $scattertimemsg .  ' <br/> ' . $mod->get_completedmsg(),
            'modid' => $mod->get_id(),
        ];
        return $this->render_from_template('mod_wordcards/finish_page', $data);
    }

    public function local_speechcards(mod_wordcards_module $mod){
        $widgetid = \html_writer::random_id();
        $definitions = $mod->get_local_terms();
        $jsonstring=$this->make_json_string($definitions);
        //$jsonstring = $this->fetch_data_json_feelings();
        $opts_html = \html_writer::tag('input', '', array('id' => $widgetid, 'type' => 'hidden', 'value' => $jsonstring));

        //need to check cards_page.mustache but i think we do not need 'hascontinue' feature
        ///$hascontinue = true;

        $completeafterlocal = $mod->completeafterlocal();
        $nexturl = empty($completeafterlocal) ? (new moodle_url('/mod/wordcards/global.php', ['id' => $mod->get_cmid()]))->out(true)
            : (new moodle_url('/mod/wordcards/finish.php', ['id' => $mod->get_cmid(), 'sesskey' => sesskey()]))->out(true);
        $opts=array('widgetid'=>$widgetid,'dryRun'=> $mod->can_manage(),'nexturl'=>$nexturl);
        $this->page->requires->js_call_amd("mod_wordcards/speechcards", 'init', array($opts));

        $data = [];
        $matching = $this->render_from_template('mod_wordcards/speechcards', $data);
        return $opts_html . $matching;

    }

    public function local_page(mod_wordcards_module $mod) {
        $definitions = $mod->get_local_terms();

        $completeafterlocal = $mod->completeafterlocal();

        $data = [
            'canmanage' => $mod->can_manage(),
            'continue' => get_string('continue'),
            'congrats' => get_string('congrats', 'mod_wordcards'),
            'definitionsjson' => json_encode(array_values($definitions)),
            'finishscatterin' => get_string('finishscatterin', 'mod_wordcards'),
            'finishedscattermsg' => $mod->get_finishedscattermsg(),
            'modid' => $mod->get_id(),
            'hascontinue' => true,
            'completeafterlocal' => $completeafterlocal,
            'nexturl' => empty($completeafterlocal) ? (new moodle_url('/mod/wordcards/global.php', ['id' => $mod->get_cmid()]))->out(true)
                : (new moodle_url('/mod/wordcards/finish.php', ['id' => $mod->get_cmid(), 'sesskey' => sesskey()]))->out(true),
        ];

        return $this->render_from_template('mod_wordcards/cards_page', $data);
    }

    public function navigation(mod_wordcards_module $mod, $currentstate) {
        $tabtree = mod_wordcards_helper::get_tabs($mod, $currentstate);
        if ($mod->can_manage()) {
            // Teachers see the tabs, as normal tabs.
            return $this->render($tabtree);
        }

        $seencurrent = false;
        $step = 1;
        $tabs = array_map(function($tab) use ($seencurrent, $currentstate, &$step, $tabtree) {
            $current = $tab->id == $currentstate;
            $seencurrent = $current || $seencurrent;
            return [
                'id' => $tab->id,
                'url' => $tab->link,
                'text' => $tab->text,
                'title' => $tab->title,
                'current' => $tab->selected,
                'inactive' => $tab->inactive,
                'last' => $step == count($tabtree->subtree),
                'step' => $step++,
            ];
        }, $tabtree->subtree);

        $data = [
            'tabs' => $tabs
        ];
        return $this->render_from_template('mod_wordcards/student_navigation', $data);
    }

    public function global_page(mod_wordcards_module $mod) {
        list($state) = $mod->get_state();
        $definitions = $mod->get_global_terms();

        $data = [
            'canmanage' => $mod->can_manage(),
            'continue' => get_string('continue'),
            'congrats' => get_string('congrats', 'mod_wordcards'),
            'definitionsjson' => json_encode(array_values($definitions)),
            'finishscatterin' => get_string('finishscatterin', 'mod_wordcards'),
            'finishedscattermsg' => $mod->get_finishedscattermsg(),
            'modid' => $mod->get_id(),
            'isglobalcompleted' => $state == mod_wordcards_module::STATE_END,
            'hascontinue' => $state != mod_wordcards_module::STATE_END,
            'nexturl' => (new moodle_url('/mod/wordcards/finish.php',
                ['id' => $mod->get_cmid(), 'sesskey' => sesskey()]))->out(true),
            'isglobalscatter' => true
        ];

        return $this->render_from_template('mod_wordcards/cards_page', $data);
    }

}
