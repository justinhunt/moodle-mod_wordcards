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
 * Module.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

use mod_wordcards\constants;
use mod_wordcards\utils;

/**
 * Module class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_module {

    const STATE_TERMS = 'terms';
    const STATE_STEP1 = 'step1practicetype';
    const STATE_STEP2 = 'step2practicetype';
    const STATE_STEP3 = 'step3practicetype';
    const STATE_STEP4 = 'step4practicetype';
    const STATE_STEP5 = 'step5practicetype';
    const STATE_END = 'end';

    const WORDPOOL_LEARN = 0;
    const WORDPOOL_REVIEW = 1;
    const WORDPOOL_MY_WORDS = 2;
    const PRACTICETYPE_SCATTER = -1;// not used
    const PRACTICETYPE_SCATTER_REV = -2;// not used
    const PRACTICETYPE_NONE = 0;
    const PRACTICETYPE_MATCHSELECT = 1;
    const PRACTICETYPE_MATCHTYPE = 2;
    const PRACTICETYPE_DICTATION = 3;
    const PRACTICETYPE_SPEECHCARDS = 4;
    const PRACTICETYPE_LISTENCHOOSE = 9;
    const PRACTICETYPE_SPACEGAME = 11;
    const PRACTICETYPE_WORDPREVIEW = 13;

    const PRACTICETYPE_MATCHSELECT_REV = 5;
    const PRACTICETYPE_MATCHTYPE_REV = 6;
    const PRACTICETYPE_DICTATION_REV = 7;
    const PRACTICETYPE_SPEECHCARDS_REV = 8;
    const PRACTICETYPE_LISTENCHOOSE_REV = 10;
    const PRACTICETYPE_SPACEGAME_REV = 12;
    const PRACTICETYPE_WORDPREVIEW_REV = 14;

    protected static $states = [
        self::STATE_TERMS,
        self::STATE_STEP1,
        self::STATE_STEP2,
        self::STATE_STEP3,
        self::STATE_STEP4,
        self::STATE_STEP5,
        self::STATE_END,
    ];

    protected $course;
    protected $cm;
    protected $context;
    protected $mod;

    protected function __construct($course, $cm, $mod = null) {
        global $DB;
        $this->course = $course;
        $this->cm = $cm;
        $this->mod = $DB->get_record('wordcards', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = context_module::instance($cm->id);
    }

    protected function course_has_term($termid) {
        global $DB;
        $sql = "SELECT 'x'
                  FROM {wordcards_terms} t
                  JOIN {wordcards} f
                    ON t.modid = f.id
                 WHERE t.id = ?
                   AND f.course = ?
                   AND t.deleted = 0";
        return $DB->record_exists_sql($sql, [$termid, $this->get_course()->id]);
    }

    public function register_module_viewed() {
        // Trigger module viewed event.
        $event = \mod_wordcards\event\course_module_viewed::create([
                'objectid' => $this->mod->id,
                'context' => $this->context,
        ]);
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('course', $this->course);
        $event->add_record_snapshot(constants::M_MODNAME, $this->mod);
        $event->trigger();
    }

    public function completion_module_viewed() {
        // Register completion based on view
        $completion = new completion_info($this->course);
        $completion->set_module_viewed($this->cm);
    }

    public function delete() {
        global $DB;
        $modid = $this->get_id();
        $DB->execute('DELETE FROM {wordcards_seen}
                       WHERE termid IN (
                            SELECT t.id
                              FROM {wordcards_terms} t
                             WHERE t.modid = ?
                            )', [$modid]);
        $DB->execute('DELETE FROM {wordcards_associations}
                       WHERE termid IN (
                            SELECT t.id
                              FROM {wordcards_terms} t
                             WHERE t.modid = ?
                            )', [$modid]);
        $DB->delete_records(constants::M_TERMSTABLE, ['modid' => $modid]);
        $DB->delete_records(constants::M_ATTEMPTSTABLE, ['modid' => $modid]);
        $DB->delete_records('wordcards', ['id' => $modid]);
    }

    public function delete_term($termid) {
        global $DB;
        $DB->set_field(constants::M_TERMSTABLE, 'deleted', 1, ['modid' => $this->get_id(), 'id' => $termid]);
    }

    public function fetch_step_termcount($step) {
        global $DB, $USER;
        $termcount = 0;
        switch($step){
            case self::STATE_STEP1:
                $termcount = $this->mod->step1termcount;
                break;
            case self::STATE_STEP2:
                $termcount = $this->mod->step2termcount;
                break;
            case self::STATE_STEP3:
                $termcount = $this->mod->step3termcount;
                break;
            case self::STATE_STEP4:
                $termcount = $this->mod->step4termcount;
                break;
            case self::STATE_STEP5:
                $termcount = $this->mod->step5termcount;
                break;
            case self::STATE_END:
            case self::STATE_TERMS:
            default:
                // do nothing
                break;
        }
        return $termcount;
    }

    public static function get_all_states() {
        return self::$states;
    }

    public static function get_wordpools() {
        $refclass = new ReflectionClass(__CLASS__);
        $constants = $refclass->getConstants();
        $pools = [];
        foreach ($constants as $k => $v) {
            if (substr($k, 0, 9) == 'WORDPOOL_') {
                $pools[$k] = $v;
            }
        }
        return $pools;
    }

    public function get_allowed_states() {
        // if we are an admin/teacher kind of person we can see all the steps
        if ($this->can_manage() || $this->can_viewreports()) {
            return self::$states;
        }

        list($state) = $this->get_state();
        if ($state == self::STATE_END) {
            // we used to allow people to retry the different states
            // but they got confused and thought they were re-attempting
            // so now they can not
            // return self::$states;
            return [self::STATE_TERMS];
        }
        return [$state];
    }

    public function get_cm() {
        return $this->cm;
    }

    public function get_mod() {
        return $this->mod;
    }

    public function get_cmid() {
        return $this->cm->id;
    }

    public function get_context() {
        return $this->context;
    }

    public function get_course() {
        return $this->course;
    }

    public function get_id() {
        return $this->mod->id;
    }


    public function get_practicetype($state) {
        switch($state){
            case self::STATE_STEP1:
                return $this->mod->step1practicetype;
            case self::STATE_STEP2:
                return $this->mod->step2practicetype;
            case self::STATE_STEP3:
                return $this->mod->step3practicetype;
            case self::STATE_STEP4:
                return $this->mod->step4practicetype;
            case self::STATE_STEP5:
                return $this->mod->step5practicetype;
        }
    }

    public function get_wordpool($state) {
        $practicetype = $this->get_practicetype($state);
        switch($practicetype ){
            case self::PRACTICETYPE_SCATTER:
            case self::PRACTICETYPE_MATCHSELECT:
            case self::PRACTICETYPE_MATCHTYPE:
            case self::PRACTICETYPE_DICTATION:
            case self::PRACTICETYPE_SPEECHCARDS:
            case self::PRACTICETYPE_LISTENCHOOSE:
            case self::PRACTICETYPE_SPACEGAME:
            case self::PRACTICETYPE_WORDPREVIEW:
                return self::WORDPOOL_LEARN;

            case self::PRACTICETYPE_MATCHSELECT_REV:
            case self::PRACTICETYPE_MATCHTYPE_REV:
            case self::PRACTICETYPE_DICTATION_REV:
            case self::PRACTICETYPE_SPEECHCARDS_REV:
            case self::PRACTICETYPE_LISTENCHOOSE_REV:
            case self::PRACTICETYPE_SPACEGAME_REV:
            case self::PRACTICETYPE_WORDPREVIEW_REV:
            default:
                return self::WORDPOOL_REVIEW;
        }
    }

    public function insert_learned_state($terms, $userid=null) {
        global $DB, $USER;

        $learnpoint = $this->mod->learnpoint;
        if($userid == null){
            $userid = $USER->id;
        }

        $sql = "SELECT t.id, a.successcount, a.selfclaim
                  FROM {wordcards_terms} t
                  JOIN {wordcards_associations} a
                    ON a.termid = t.id
                 WHERE a.userid = ?
                   AND t.modid = ?
                   AND t.deleted = 0";

        $result = $DB->get_records_sql($sql, [$userid, $this->get_id()]);
        if($result) {
            foreach ($terms as $term) {
                $term->selfclaim = 0;
                $term->learned = 0;
                $term->learned_progress = 0;
                if (isset($result[$term->id])){
                    if($result[$term->id]->successcount >= $learnpoint) {
                        $term->learned = true; //$result[$term->id];
                        $term->learned_progress = 100;
                    }else{
                        $term->learned = false;
                        $term->learned_progress = round($result[$term->id]->successcount / $learnpoint * 100);
                    }
                    $term->selfclaim = $result[$term->id]->selfclaim;
                }
            }
        }
        return $terms;
    }

    public static function insert_media_urls($terms) {
        global $CFG;
        foreach($terms as $term){
            $contextid = false;
            $cachebuster = '?cb=' . \html_writer::random_id();
            if($term->image){
                if(!$contextid){
                    $thecm = get_coursemodule_from_instance('wordcards', $term->modid, 0, false, MUST_EXIST);
                    $contextid = context_module::instance($thecm->id)->id;
                }
                $term->image = "$CFG->wwwroot/pluginfile.php/$contextid/mod_wordcards/image/$term->id" . $cachebuster;
            }
            if($term->audio){
                if(!$contextid){
                    $thecm = get_coursemodule_from_instance('wordcards', $term->modid, 0, false, MUST_EXIST);
                    $contextid = context_module::instance($thecm->id)->id;
                }
                $term->audio = "$CFG->wwwroot/pluginfile.php/$contextid/mod_wordcards/audio/$term->id  . $cachebuster";
            }

            if($term->model_sentence_audio){
                if(!$contextid){
                    $thecm = get_coursemodule_from_instance('wordcards', $term->modid, 0, false, MUST_EXIST);
                    $contextid = context_module::instance($thecm->id)->id;
                }

                $term->model_sentence_audio = "$CFG->wwwroot/pluginfile.php/$contextid/mod_wordcards/model_sentence_audio/$term->id  . $cachebuster";
            }
        }
        return $terms;
    }

    /*
     * If there is a need we can run format_string over the definition
     */

    public static function format_defs($terms) {
        global $CFG;
        foreach($terms as $def){
            // lets not double up
            if(strpos($def->definition, '<div class="text_to_html">') !== 0) {
                $def->definition = format_text($def->definition);
            }
        }
        return $terms;
    }

    public function update_userpref_defs($terms){
        // Get selected definitions language.
        $activitydefinitionslanguage = $this->get_mod()->deflanguage;
        $userprefdeflanguage = get_user_preferences('wordcards_deflang');
 
        // If there is no userpref or its the same as the activity def, nothing to update.
        if (empty($userprefdeflanguage) || $userprefdeflanguage == $activitydefinitionslanguage) {
            return $terms;
        }
        //otherwise get the translation that suits
        foreach ($terms as $term) {
            if (empty($term->translations)) {continue;}
            if (!self::is_json($term->translations)) {continue;}
            $translations = json_decode($term->translations);
            // English is a special case.
            if ($userprefdeflanguage == 'en') {
                $translations->en = $term->sourcedef;
            }
            if (!empty($translations) &&
                isset($translations->{$userprefdeflanguage})) {
                    $term->definition = $translations->{$userprefdeflanguage};
            }
        }
        return $terms;
    }


    public function get_learn_terms(int $maxterms) {
        $records = $this->get_terms();
        if (!$records) {
            return [];
        }
        shuffle($records);
        if ($maxterms > 0) {
            $selectedrecords = array_slice($records, 0, $maxterms);
            $selectedrecords = self::insert_media_urls($selectedrecords);
            $selectedrecords = self::format_defs($selectedrecords);
            return $selectedrecords;
        } else {
            $records = self::insert_media_urls($records);
            $records = self::format_defs($records);
            return $records;
        }
    }

    public function get_allreview_terms($countonly = false) {
        global $DB, $USER;
        $params = ['userid' => $USER->id, 'modid' => $this->cm->instance, 'courseid' => $this->cm->course];
        $reviewsql = $countonly ? "SELECT COUNT(t.id)" : "SELECT t.*";
                $reviewsql .= " FROM {wordcards_terms} t INNER JOIN {wordcards} w ON w.id = t.modid ";
                $reviewsql .= " LEFT OUTER JOIN {wordcards_seen} s ON s.termid = t.id AND t.deleted = 0 AND s.userid = :userid";
                $reviewsql .= " WHERE t.deleted = 0 AND NOT t.modid = :modid AND s.id IS NOT NULL AND w.course = :courseid";
        if ($countonly) {
            return $DB->get_field_sql($reviewsql, $params);
        } else {
            $records = $DB->get_records_sql($reviewsql, $params);
            if (!$records) {
                return [];
            }
            shuffle($records);
            $records = self::insert_media_urls($records);
            $records = self::format_defs($records);
            return $records;
        }
    }

    public function get_review_terms(int $maxterms) {
        global $DB, $USER;
        // Old code had a min of 4 so keeping this - not sure why.
        $maxterms = max(4, $maxterms);
        $from = 0;
        $limit = $maxterms + 5;

        // Figure out what modules are visible to the user.
        $modinfo = get_fast_modinfo($this->get_course());
        $cms = $modinfo->get_instances_of('wordcards');
        $allowedmodids = [];
        foreach ($cms as $cm) {
            // we only want visible mods, and not the current activity
            if ($cm->uservisible && $cm->id != $this->cm->id) {
                $allowedmodids[] = $cm->instance;
            }
        }
        if (empty($allowedmodids)) {
            return [];
        }
        list($insql, $inparams) = $DB->get_in_or_equal($allowedmodids, SQL_PARAMS_NAMED, 'param', true);
        $params = $inparams;

        if ($this->can_manage()) {
            // Teachers see any record randomly ordered.
            $selectsql = "SELECT t.* ";
            $countsql = "SELECT COUNT(t.id) ";
            $sql = " FROM {wordcards_terms} t
                      JOIN {wordcards} f
                        ON f.id = t.modid
                     WHERE t.deleted = 0        -- The term was not deleted
                     AND f.ttslanguage='" . $this->mod->ttslanguage . "' -- The tts language is the same as the module
                       AND f.id $insql          -- The user has access to the module in which the term is.
                   ";
            // This is the way to make it simili random, we extract a random subset.
            $from = rand(0, $DB->count_records_sql($countsql . $sql, $params) - $maxterms - 1);
            if($from < 0){$from = 0;
            }

            $sql = $selectsql . $sql;
        } else {
            $sql = "SELECT t.*
                      FROM {wordcards_terms} t
                      JOIN {wordcards} f
                        ON f.id = t.modid
                 LEFT JOIN {wordcards_associations} a  -- Link the associations, if any.
                        ON a.termid = t.id
                       AND a.userid = :userid2
                       AND f.ttslanguage='" . $this->mod->ttslanguage . "' -- The tts language is the same as the module
                     WHERE t.deleted = 0        -- The term was not deleted.
                       AND f.id $insql          -- The user has access to the module in which the term is.
                  ORDER BY
                           -- Prioritise the terms which have never been associated, associated once or associated twice.
                           CASE WHEN a.id IS NULL THEN '1'
                                WHEN (COALESCE(a.successcount) + COALESCE(a.failcount)) = 1 THEN '2'
                                WHEN (COALESCE(a.successcount) + COALESCE(a.failcount)) = 2 THEN '3'
                                ELSE '4'
                                END ASC,
                           -- Prioritise the terms with the highest failure ratio. We multiply by 1.0 to make it a float.
                           CASE WHEN (COALESCE(a.successcount) + COALESCE(a.failcount)) = 0 THEN '0'
                                ELSE 1.0 * COALESCE(a.failcount) / (COALESCE(a.successcount) + COALESCE(a.failcount))
                                END DESC,
                           -- Prioritise by the least attempted.
                           (COALESCE(a.successcount) + COALESCE(a.failcount)) ASC,
                           -- Prioritise by the oldest attempts.
                           CASE WHEN COALESCE(a.lastsuccess) < COALESCE(a.lastfail) THEN COALESCE(a.lastsuccess)
                                ELSE COALESCE(a.lastfail)
                                END ASC";
            $params += [
                'userid1' => $USER->id,
                'userid2' => $USER->id,
            ];
        }

        // Select a few more records to make it a bit more random, and more fun.
        $records = $DB->get_records_sql($sql, $params, $from, $limit);
        if (!$records) {
            return [];
        }
        shuffle($records);
        if($maxterms > 0) {
            $selectedrecords = array_slice($records, 0, $maxterms);
            $selectedrecords = self::insert_media_urls($selectedrecords);
            $selectedrecords = self::format_defs($selectedrecords);
            return $selectedrecords;
        }else{
            $records = self::insert_media_urls($records);
            $records = self::format_defs($records);
            return $records;
        }

    }

    public function get_attempts() {
        global $DB, $USER;

        // Teachers are always considered done.
        if ($this->can_manage()) {
            return [self::STATE_END, null];
        }

        $records = $DB->get_records('wordcards_progress', ['modid' => $this->get_id(), 'userid' => $USER->id]);
        return $records;
    }

    public function get_latest_attempt() {
        global $USER, $DB;
        $records = $DB->get_records('wordcards_progress', ['modid' => $this->get_id(), 'userid' => $USER->id], 'timecreated DESC', '*', 0, 1);
        if(!$records){
            return false;
        }else{
            return array_shift($records);
        }
    }

    // can they use free mode
    public function can_free_mode() {

        switch($this->mod->journeymode){
            // steps mode, no
            case constants::MODE_STEPS:
                return false;

            // steps then free, if they have a completed attempt they can
            case constants::MODE_STEPSTHENFREE:
                // if no attempts, we can attempt
                $attempts = $this->get_attempts();
                if($attempts){
                    return true;
                }else{
                    return false;
                }

                // free mode, or otherwise (there is no otherwise..) they can
            case constants::MODE_FREE:
            default:
                return true;
        }
    }

     // can they use sesson mode
    public function can_session_mode() {
        switch($this->mod->journeymode){
            // Free mode, or otherwise (there is no otherwise..) they can.
            case constants::MODE_SESSION:
                return true;
            default:
                return false;
        }
    }

    public function can_attempt() {
        // unlimited attempts can attempt
        if($this->mod->maxattempts == 0){return true;
        }

        // Teachers can attempt.
        if($this->can_manage()){return true;
        }

        // If teachers, we can attempt
        if ($this->can_manage()) {return true;
        }

        // if no attempts, we can attempt
        $attempts = $this->get_attempts();
        if(!$attempts){return true;
        }

        // if we have fewer attempts than the max, we can attemopt
        if(count($attempts) < $this->mod->maxattempts){return true;
        }

        // if we have not completed the last attempt, we can attempt
        if(!$this->has_user_finished_latest_attempt()){return true;
        }

        // otherwise, no we can not attempt
        return false;
    }

    public function get_state() {
        global $DB, $USER;

        // Teachers are always considered done.
        if ($this->can_manage() || $this->can_viewreports()) {
            return [self::STATE_END, null];
        }

        $record = $this->get_latest_attempt();
        if(!$record){
            return [self::STATE_TERMS, null];
        }else{
            return [$record->state, json_decode($record->statedata)];
        }
    }

    public function get_terms($includedeleted = false) {
        global $DB;
        $params = ['modid' => $this->mod->id];
        if (!$includedeleted) {
            $params['deleted'] = 0;
        }
        $terms = $DB->get_records('wordcards_terms', $params, 'id ASC');
        if($terms){
            $terms = self::insert_media_urls($terms);
            $terms = $this->update_userpref_defs($terms);
            $terms = self::format_defs($terms);
        }
        return $terms;
    }

    public function get_terms_count($includedeleted = false) {
        global $DB;
        $params = ['modid' => $this->mod->id];
        if (!$includedeleted) {
            $params['deleted'] = 0;
        }
        $termcount = $DB->count_records('wordcards_terms', $params);
        return $termcount;
    }

    protected function has_completed_state($state) {
        global $DB, $USER;

        $sql = "SELECT COUNT('x')
                  FROM {wordcards_terms} t
                  JOIN {wordcards_associations} a
                    ON a.termid = t.id
                 WHERE a.userid = ?
                   AND t.modid = ?
                   AND t.deleted = 0
                   AND a.successcount > 0";

        switch($state){
            case self::STATE_STEP1:
                $termcount = $this->mod->step1termcount;
                break;
            case self::STATE_STEP2:
                $termcount = $this->mod->step2termcount;
                break;
            case self::STATE_STEP3:
                $termcount = $this->mod->step3termcount;
                break;
            case self::STATE_STEP4:
                $termcount = $this->mod->step4termcount;
                break;
            case self::STATE_STEP5:
                $termcount = $this->mod->step5termcount;
                break;
            default:
                $termcount = 0;
        }

        // Completed when there is enough successful associations.
        // we could set passmark to 1 or half of termcount?
        // $passmark = $termcount;
        $passmark = 1;
        return $DB->count_records_sql($sql, [$USER->id, $this->get_id()]) >= $passmark;
    }

    public function mark_terms_as_seen() {
        global $DB, $USER;
        $terms = self::get_terms();
        foreach($terms as $term){
            $params = ['userid' => $USER->id, 'termid' => $term->id];
            if (!$DB->record_exists('wordcards_seen', $params)) {
                $record = (object)$params;
                $record->timecreated = time();
                $DB->insert_record('wordcards_seen', $record);
            }
        }
    }

    public function mark_terms_as_unseen() {
        global $DB, $USER;
        $terms = self::get_terms();
        foreach($terms as $term){
            $params = ['userid' => $USER->id, 'termid' => $term->id];
            $DB->delete_records('wordcards_seen', $params);
        }
    }


    public function has_seen_all_terms() {
        global $DB, $USER;

        // TO DO remove this code, terms are always seen
        if (!$this->has_terms()) {
            return false;
        }

        $sql = "SELECT 'x'
                  FROM {wordcards_terms} t
             LEFT JOIN {wordcards_seen} s
                    ON t.id = s.termid
                   AND s.userid = ?
                 WHERE t.deleted = 0
                   AND t.modid = ?
                   AND s.id IS NULL";

        // We've seen it all when there is no null entries.
        return !$DB->record_exists_sql($sql, [$USER->id, $this->get_id()]);
    }

    public function has_terms() {
        global $DB;
        return $DB->record_exists('wordcards_terms', ['modid' => $this->get_id()]);
    }

    public function has_user_finished_latest_attempt() {
        $record = $this->get_latest_attempt();
        return $record && $record->state == self::STATE_END;
    }

    public function has_user_finished_an_attempt() {
        $records = $this->get_attempts();
        if ($records) {
            foreach ($records as $record) {
                if ($record && isset($record->state) && $record->state == self::STATE_END) {
                    return true;
                }
            }
        }
        return false;
    }

    public function has_user_learned_all_terms() {
        global $DB, $USER;

        $learnedcount = $this->get_user_learned_count();
        $termcount = $this->get_terms_count();
        return $learnedcount >= $termcount;
    }

    public function get_user_learned_count() {
        global $DB, $USER;

        $sql = "SELECT t.id, a.successcount
                  FROM {wordcards_terms} t
                  JOIN {wordcards_associations} a
                    ON a.termid = t.id
                 WHERE a.userid = ?
                   AND t.modid = ?
                   AND t.deleted = 0
                   AND a.successcount >= ?";

        $learned = $DB->get_records_sql($sql, [$USER->id, $this->get_id(), $this->mod->learnpoint]);
        $learnedcount = 0;
        if($learned){$learnedcount = count($learned);}
        return $learnedcount;
    }

    public function is_completion_enabled() {
        return !empty($this->mod->completionwhenfinish) || !empty($this->mod->completionwhenlearned);
    }

    public function record_failed_association($term, $term2id=0) {
        global $DB, $USER;

        if ($term->modid != $this->get_id()) {
            throw new coding_exception('Invalid argument received, first term must belong to this module.');
        } else if ($term2id > 0 && !$this->course_has_term($term2id)) {
            throw new coding_exception('Unexpected association');
        }

        $params = ['userid' => $USER->id, 'termid' => $term->id];
        if (!($record1 = $DB->get_record(constants::M_ASSOCTABLE, $params))) {
            $record1 = (object) $params;
            $record1->failcount = 0;
        }

        $record1->failcount += 1;
        $record1->lastfail = time();
        if (empty($record1->id)) {
            $DB->insert_record(constants::M_ASSOCTABLE, $record1);
        } else {
            $DB->update_record(constants::M_ASSOCTABLE, $record1);
        }

        if($term2id > 0) {
            $params = ['userid' => $USER->id, 'termid' => $term2id];
            if (!($record2 = $DB->get_record(constants::M_ASSOCTABLE, $params))) {
                $record2 = (object)$params;
                $record2->failcount = 0;
            }
            $record2->failcount += 1;
            $record2->lastfail = time();
            if (empty($record2->id)) {
                $DB->insert_record(constants::M_ASSOCTABLE, $record2);
            } else {
                $DB->update_record(constants::M_ASSOCTABLE, $record2);
            }
        }
    }

    public function record_successful_association($term) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'termid' => $term->id];
        if (!($record = $DB->get_record(constants::M_ASSOCTABLE, $params))) {
            $record = (object) $params;
            $record->successcount = 0;
        }

        $record->successcount += 1;
        $record->lastsuccess = time();

        if (empty($record->id)) {
            $DB->insert_record(constants::M_ASSOCTABLE, $record);
        } else {
            $DB->update_record(constants::M_ASSOCTABLE, $record);
        }

         // Raise word learned event.
        if ($record->successcount === (int)$this->mod->learnpoint) {
            $theevent = \mod_wordcards\event\word_learned::create_from_term($term, $this->context, $record);
            $theevent->trigger();

        }
    }

    public function record_successful_learn($term, $selfclaim = false) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'termid' => $term->id];
        if (!($record = $DB->get_record(constants::M_ASSOCTABLE, $params))) {
            $record = (object) $params;
        }

        $record->successcount = (int)$this->mod->learnpoint;
        $record->lastsuccess = time();

        // If it has been self claimed as already learned we flag that so we can give better reports on in session learns.
        if ($selfclaim) {
            $record->selfclaim = 1;
        }

        if (empty($record->id)) {
            $DB->insert_record(constants::M_ASSOCTABLE, $record);
        } else {
            $DB->update_record(constants::M_ASSOCTABLE, $record);
        }

         // Raise word learned event if it was not a self claim
        if (!$selfclaim) {
            $theevent = \mod_wordcards\event\word_learned::create_from_term($term, $this->context, $record);
            $theevent->trigger();
        }
    }

    public function resume_progress($currentstate) {
        $this->update_state($currentstate);
        list($state, $statedata) = $this->get_state();

        if ($state == self::STATE_END) {
            // They can go wherever they want when finished.
            return;

        } else if ($state == $currentstate) {
            // They do not need to be sent elsewhere.
            return;
        }

        switch ($state) {
            case self::STATE_TERMS:
                redirect(new moodle_url('/mod/wordcards/view.php', ['id' => $this->get_cmid()]));
                break;

            case self::STATE_STEP1:
            case self::STATE_STEP2:
            case self::STATE_STEP3:
            case self::STATE_STEP4:
            case self::STATE_STEP5:
                redirect(new moodle_url('/mod/wordcards/activity.php', ['id' => $this->get_cmid(), 'nextstep' => $state]));
                break;

        }
    }

    // remove a partial attempt if the user selects "cancel_attempt"
    public function remove_attempt() {
        global $DB;

        // only cancel if we have a current attempt going
        $latestattempt = $this->get_latest_attempt();
        if(!$latestattempt){
            return false;
        }
        if($latestattempt->state == self::STATE_END){
            return false;
        }
        $ret = $DB->delete_records(constants::M_ATTEMPTSTABLE, ['id' => $latestattempt->id]);
        return $ret;
    }

    // force a reattempt that will then start them off on step1 (it will bump them up from terms step)
    public function create_reattempt() {
        global $DB, $USER;

        // only reattempt if we dont have a current attempt going
        $latestattempt = $record = $this->get_latest_attempt();
        if(!$latestattempt){return false;
        }
        if($latestattempt->state != self::STATE_END){return false;
        }

        $params = ['userid' => $USER->id, 'modid' => $this->get_id()];
        $record = (object) $params;

        $record->state = self::STATE_TERMS;
        $record->statedata = '{}';
        $record->timecreated = time();
        $ret = $DB->insert_record(constants::M_ATTEMPTSTABLE, $record);
        return $ret;

    }

    protected function set_state($state, $statedata = null) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'modid' => $this->get_id()];
        if ($record = $this->get_latest_attempt()) {
        } else {
            $record = (object) $params;
        }

        $record->state = $state;
        $record->statedata = json_encode($statedata);
        if (!empty($record->id)) {
            $DB->update_record(constants::M_ATTEMPTSTABLE, $record);
        } else {
            $record->timecreated = time();
            $record->id = $DB->insert_record(constants::M_ATTEMPTSTABLE, $record);
            // lazy, just fetch back the record so we have all the fields, and later triggered events have all they need
            $record = $DB->get_record(constants::M_ATTEMPTSTABLE, ['id' => $record->id]);
        }
        // raise step submitted event
        \mod_wordcards\event\step_submitted::create_from_attempt($record, $this->context, $state)->trigger();

        // The user finished the activity, notify the completion API.
        if ($state == self::STATE_END){
            // raise attempt submitted event
            \mod_wordcards\event\attempt_submitted::create_from_attempt($record, $this->context)->trigger();
            if($this->is_completion_enabled()){
                $completion = new completion_info($this->get_course());
                if ($completion->is_enabled($this->get_cm())) {
                    $completion->update_state($this->get_cm(), COMPLETION_COMPLETE);
                }
            }
        }
    }

    /**
     * Updates the states.
     *
     * This checks whether the user should be jumping to the next state
     * because they have completed what they had to.
     *
     * @param string $requestedstate The state which the user is trying to move to.
     * @return void
     */
    protected function update_state($requestedstate) {
        list($state) = $this->get_state();

        if ($state == self::STATE_END) {
            // Nothing to do.
            return;

        } else if ($state == self::STATE_TERMS) {
            if ( $this->has_seen_all_terms()) {
                    $nextstep = $this->get_next_step(self::STATE_TERMS);
                    $this->set_state($nextstep);
            }

        } else if ($this->has_completed_state($state)) {
                $nextstep = $this->get_next_step($state);
                $this->set_state($nextstep);
        }
    }

    // Factories.
    public static function get_by_cmid($cmid) {
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'wordcards');
        return new static($course, $cm);
    }

    public static function get_by_modid($modid) {
        list($course, $cm) = get_course_and_cm_from_instance($modid, 'wordcards');
        return new static($course, $cm);
    }

    // Capabilities.
    public function can_manage() {
        return  has_capability('mod/wordcards:manage', $this->context);
    }
    public function can_push() {
        return  has_capability('mod/wordcards:push', $this->context) &&
        $this->get_mod()->masterinstance;
    }
    public function require_manage() {
        require_capability('mod/wordcards:addinstance', $this->context);
    }
    public function can_viewreports() {
        return  has_capability('mod/wordcards:viewreports', $this->context);
    }
    public function require_viewreports() {
        require_capability('mod/wordcards:viewreports', $this->context);
    }
    public function can_view() {
        return  has_capability('mod/wordcards:view', $this->context);
    }

    public function require_view() {
        self::require_view_in_context($this->context);
    }

    public static function require_view_in_context(context $context) {
        require_capability('mod/wordcards:view', $context);
    }

    public function get_next_step($currentstep) {
        global $USER, $DB;

        // if we already ended, lets return that
        if($currentstep == self::STATE_END){
            return $currentstep;
        }

        // TODO: add any newly created steps to this array to add them to the search
        $steps = [self::STATE_TERMS,
                self::STATE_STEP1,
                self::STATE_STEP2,
                self::STATE_STEP3,
                self::STATE_STEP4,
                self::STATE_STEP5,
                self::STATE_END];
        // init our search start flag and return value
        $searching = false;
        $nextstep = false;

        // loop through the steps
        foreach($steps as $thisstep){
            if($currentstep == $thisstep){
                $searching = true;
                continue;
            }
            // we loop through till we are beyond the current step, and then we are "searching" for the next step
            if($searching){
                // if the next step is the end step, then so be it.
                if($thisstep == self::STATE_END){
                    $nextstep = $thisstep;
                    break;
                }
                // check if we have words in the review pool, and if the currebt "next" activity is a "learn" or "review" one
                // we stopped skipping activities if the review pool empty, and began usng learn terms: because grading got hard
                $arewordstoreview = true;// $this->are_there_words_to_review();
                $nextpracticetype = $this->mod->{$thisstep};//'step1practice' or 'step2practice' db field

                // if not practice type was specified move on
                if ($nextpracticetype == self::PRACTICETYPE_NONE){
                    continue;
                }

                // get next word poodl
                $nextwordpool = self::get_wordpool($thisstep);

                // if we have words in the review pool, then this step should be fine
                if($arewordstoreview){
                    $nextstep = $thisstep;
                    break;

                    // if we have no words in the review pool, we need a learn step, lets see if we have one
                }else if($nextwordpool == self::WORDPOOL_LEARN){
                    $nextstep = $thisstep;
                    break;
                }else{
                    // we would continue if we need a learn activity, but $thisstep was a review activity
                    continue;
                }
            }
        }
        // if we got no next step, then lets just jump to end
        if(!$nextstep){$nextstep = self::STATE_END;
        }
        // return next step
        return $nextstep;
    }

    public function are_there_words_to_review($userid = null) {
        global $USER, $DB;

        // if we are an admin, just say yes
        if($this->can_manage()){
            return true;
        }

        if (empty($userid)) {
            $userid = $USER->id;
        }

            // Retrieve the list of wordcard modids of the course.
            $modids = [];

        foreach(get_fast_modinfo($this->course)->get_instances_of('wordcards') as $wordcard) {
            $modids[] = $wordcard->instance;
        }

            $params = ['state' => self::STATE_END, 'userid' => $userid];
            list($sqlmodidtest, $modidparams) = $DB->get_in_or_equal($modids, SQL_PARAMS_NAMED);
            $params = array_merge($params, $modidparams);
            $sqlmodidtest = 'AND modid ' . $sqlmodidtest;

        $completedwordcardtotal = $DB->count_records_select('wordcards_progress',
                'state = :state AND userid = :userid ' . $sqlmodidtest, $params);

        return (!empty($completedwordcardtotal));

    }

    public function set_region_passagehash() {
        global $DB;
        if(utils::needs_lang_model($this)){
            $region = get_config(constants::M_COMPONENT, 'awsregion');
            $newpassagehash = utils::fetch_passagehash($this);
            if($newpassagehash){
                // check if it has changed, if not do not waste time processing it
                if($this->get_mod()->passagehash != ($region . '|' . $newpassagehash)) {
                    // build a lang model
                    $ret = utils::fetch_lang_model($this);
                    if ($ret && isset($ret->success) && $ret->success)  {
                        $regionpassagehash = $region . '|' . $newpassagehash;
                        $DB->update_record('wordcards', ['id' => $this->get_mod()->id, 'passagehash' => $regionpassagehash, 'hashisold' => 0]);
                        return $regionpassagehash;
                    }
                }
            }
        }
        // by default just return what already exists, but also update our "dirty" flag so we do not keep coming back here
        $DB->update_record('wordcards', ['id' => $this->get_mod()->id, 'hashisold' => 0]);
        return $this->get_mod()->passagehash;
    }

    public function export_simple_terms_to_csv($delim = "\t") {
        global $DB;
        // fetch terms to return as csv
        $terms = $DB->get_records('wordcards_terms', ['modid' => $this->mod->id, 'deleted' => 0 ], 'id ASC');
        if(!$terms){return '';
        }

        $ret = [];
        foreach($terms as $term){
            $termsarray = [$term->term,
                $term->definition,
                empty($term->ttsvoice) ? '' : $term->ttsvoice,
                empty($term->modelsentence) ? '' : $term->modelsentence];
            $ret[] = implode($delim, $termsarray);
        }
        $filecontent = implode("\r\n", $ret);
        return $filecontent;
    }

    // See if this is truly json or some error.
    public static function is_json($string) {
        if (!$string) {
            return false;
        }
        if (empty($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}
