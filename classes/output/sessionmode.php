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
 * Session mode class to produce data for session mode mustache.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 */

/**
 * Session mode class to produce data for session mode mustache.
 *
 * @package mod_wordcards
 * @author  David Watson - evolutioncode.uk
 */

namespace mod_wordcards\output;

use mod_wordcards\constants;
use mod_wordcards\utils;

class sessionmode implements \renderable, \templatable {

    private $cm;
    private $course;
    private $mod;
    private $practicetype;
    private $wordpool;

    public function __construct($cm, $course, int $practicetype, int $wordpool) {
        $this->cm = $cm;
        $this->course = $course;
        $this->mod = \mod_wordcards_module::get_by_cmid($cm->id);
        $this->practicetype = $practicetype;
        $this->wordpool = $wordpool;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \mod_wordcards\output\renderer $renderer The renderer
     * @return \stdClass
     */
    public function export_for_template($renderer) {

        $data = new \stdClass();

        // First check if the selected wordpool is empty.  If it is, pick another.
        $wordpoolcounts = [];
        foreach (\mod_wordcards_module::get_wordpools() as $pool) {
            $wordpoolcounts[$pool] = self::get_terms($pool, true);
        }
        if ($wordpoolcounts[$this->wordpool] <= 0) {
            foreach ($wordpoolcounts as $pool => $count) {
                if ($count > 0) {
                    $this->wordpool = $pool;
                    break;
                }
            }
        }

        $data->embed = $renderer->get_embed_flag();
        $data->pagetitle = $renderer->page_heading($this->practicetype, $this->wordpool);
        $data->id = $this->cm->id;
        $data->practicetype = $this->practicetype;
        $data->wordpool = $this->wordpool;
        $practicetypeoptions = utils::get_practicetype_options(\mod_wordcards_module::WORDPOOL_LEARN);
        $data->introactive = !$this->practicetype;
        $journeymode = $this->mod->get_mod()->journeymode;
        $data->stepsmodeavailable = ($journeymode == constants::MODE_STEPS || $journeymode == constants::MODE_SESSIONTHENFREE);
        $data->defsurl = new \moodle_url('/mod/wordcards/sessionmode.php', ['id' => $this->cm->id, 'practicetype' => 0, 'wordpool' => $this->wordpool, 'embed' => $data->embed]);
        foreach ($practicetypeoptions as $id => $title) {
            $data->tabs[] = [
                'id' => $id,
                'title' => $title,
                'active' => $id == $this->practicetype ? 1 : 0,
                'url' => new \moodle_url(
                    '/mod/wordcards/sessionmode.php', ['id' => $this->cm->id, 'practicetype' => $id, 'wordpool' => $this->wordpool, 'embed' => $data->embed]
                ),
                'icon' => utils::fetch_activity_tabicon($id),
            ];
        }

        if (!empty($this->mod->intro)) {
            $data->intro = format_module_intro('wordcards', $this->mod, $this->cm->id);
        }

        // TO DO - probably remove wordpool selection from session, but lets just get it to load
        $wordpoolicons = [
            \mod_wordcards_module::WORDPOOL_LEARN => 'fa-star-o',
            \mod_wordcards_module::WORDPOOL_REVIEW => 'fa-history',
            \mod_wordcards_module::WORDPOOL_MY_WORDS => 'fa-refresh',
            \mod_wordcards_module::WORDPOOL_SESSION_WORDS => 'fa-circle-o',
        ];

        $mywordspool = new \mod_wordcards\my_words_pool($this->cm->course);

        // Add the ids of all terms in my words pool to the page markup so that JS can see them.
        $data->mywordstermids = json_encode(array_keys($mywordspool->get_words()));
        $data->selectedpoolhaswords = 0;
        // We need to add a list of word pools to the page for the word pool select menu.
        foreach (\mod_wordcards_module::get_wordpools() as $wordpoolid) {
            $pool = (object)[
                'wordpoolid' => $wordpoolid,
                'title' => $renderer->get_wordpool_string($wordpoolid),
                'selected' => $wordpoolid == $this->wordpool,
                'icon' => isset($wordpoolicons[$wordpoolid]) ? $wordpoolicons[$wordpoolid] : 'fa-circle-o',
            ];
            $wordcount = $wordpoolcounts[$wordpoolid];
            $pool->countwordstoreview = (string)$wordcount;
            $pool->disabled = $wordcount <= 0 ? 1 : 0;
            if ($pool->selected) {
                $data->selectedwordpool = $pool->title;
                $data->selectedwordpoolicon = $pool->icon;
                $data->selectedwordpoolcountwords = $pool->countwordstoreview;
                $data->selectedpoolhaswords = (bool)$data->selectedwordpoolcountwords;
            }

            $data->wordpools[] = $pool;
        }

        // For the wordpool we show a <select> form element if the device is mobile or tablet.
        $devicetype = \core_useragent::get_device_type();
        $data->showselectmenu = in_array($devicetype, [\core_useragent::DEVICETYPE_MOBILE, \core_useragent::DEVICETYPE_TABLET]);

        if ($data->selectedpoolhaswords) {
            $definitions = $this->get_terms($this->wordpool, false, $this->practicetype);
            switch ($this->practicetype){
                case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
                case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
                case \mod_wordcards_module::PRACTICETYPE_DICTATION:
                case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE:
                    $data->mainhtml = $renderer->a4e_page($this->mod, $this->practicetype, $definitions, true);
                    break;
                case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS:
                    $data->mainhtml = $renderer->speechcards_page($this->mod, $definitions, true);
                    break;
                case \mod_wordcards_module::PRACTICETYPE_SPACEGAME:
                    $data->mainhtml = $renderer->spacegame_page($this->mod, $definitions, true);
                    break;
                default:
                    // Show the intro page and cards.
                    $data->isintropage = 1;
                    $data->definitions = $renderer->definitions_page_data($this->mod, $definitions);
                    $data->definitions['isfreemode'] = 1;
                    $data->definitions['nexturl'] = isset($data->tabs[0]['url']) ? $data->tabs[0]['url'] : '';
                    $data->definitions['introheading'] = get_string('freemode', 'mod_wordcards');
                    $stringmanager = get_string_manager();
                    $data->definitions['introstrings'] = [];
                    for ($x = 1; $x <= 10; $x++) {
                        $stringkey = 'freemodeintropara' . $x;
                        if ($stringmanager->string_exists($stringkey, 'mod_wordcards')) {
                            $data->definitions['introstrings'][] = get_string($stringkey, 'mod_wordcards');
                        } else {
                            break;
                        }
                    }
            }
        } else {
            $data->mainhtml = get_string('selectedpoolhasnowords', 'mod_wordcards');
        }

        return $data;
    }

    private function get_terms(int $wordpool, bool $countonly, int $practicetype=0) {
        global $DB, $USER;

        // SQL Params
        $params = ['userid' => $USER->id, 'modid' => $this->cm->instance, 'courseid' => $this->cm->course];

        // words to show
        if($practicetype == \mod_wordcards_module::PRACTICETYPE_NONE){
            $maxwords = 0;
        }else{
            $maxwords = get_config(constants::M_COMPONENT, 'def_wordstoshow');
        }

        // wordpool :: MY WORDS
        if ($wordpool == \mod_wordcards_module::WORDPOOL_MY_WORDS) {
            $wordpool = new \mod_wordcards\my_words_pool($this->course->id);
            return $countonly
                ? $wordpool->word_count()
                : $wordpool->get_words($maxwords);
        }

        // wordpool :: REVIEW WORDS
        if ($wordpool == \mod_wordcards_module::WORDPOOL_REVIEW) {
            // In this case we want ALL the words returned.
            if ($countonly || $practicetype == \mod_wordcards_module::PRACTICETYPE_NONE) {
                $reviewsql = $countonly ? "SELECT COUNT(t.id)" : "SELECT t.*";
                $reviewsql .= " FROM {wordcards_terms} t INNER JOIN {wordcards} w ON w.id = t.modid ";
                $reviewsql .= " LEFT OUTER JOIN {wordcards_seen} s ON s.termid = t.id AND t.deleted = 0 AND s.userid = :userid";
                $reviewsql .= " WHERE t.deleted = 0 AND NOT t.modid = :modid AND s.id IS NOT NULL AND w.course = :courseid";
                if($countonly) {
                    return $DB->get_field_sql($reviewsql, $params);
                }else{
                    $records = $DB->get_records_sql($reviewsql, $params);
                    if (!$records) {
                        return [];
                    }
                    shuffle($records);
                    $records = \mod_wordcards_module::insert_media_urls($records);
                    $records = \mod_wordcards_module::format_defs($records);
                }
            } else {
                // in this case we want words to practice returned
                return $this->mod->get_review_terms($maxwords);
            }
        }

        // wordpool :: NEW WORDS
        if ($countonly) {
            $learnsql = "SELECT COUNT(t.id)  FROM {wordcards_terms} t WHERE t.deleted = 0 AND t.modid = :modid";
            return $DB->get_field_sql($learnsql, $params);
        }else{
            return $this->mod->get_learn_terms($maxwords);
        }

    }
}
