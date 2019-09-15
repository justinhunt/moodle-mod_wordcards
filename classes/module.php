<?php
/**
 * Module.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Module class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_module {

    const STATE_TERMS = 'terms';
    const STATE_LOCAL = 'local';
    const STATE_GLOBAL = 'global';
    const STATE_END = 'end';

    const PRACTICETYPE_SCATTER = 0;
    const PRACTICETYPE_MATCHSELECT = 1;
    const PRACTICETYPE_MATCHTYPE = 2;
    const PRACTICETYPE_DICTATION = 3;
    const PRACTICETYPE_SPEECHCARDS = 4;

    protected static $states = [
        self::STATE_TERMS,
        self::STATE_LOCAL,
        self::STATE_GLOBAL,
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
        $DB->delete_records('wordcards_terms', array('modid' => $modid));
        $DB->delete_records('wordcards_progress', array('modid' => $modid));
        $DB->delete_records('wordcards', array('id' => $modid));
    }

    public function delete_term($termid) {
        global $DB;
        $DB->set_field('wordcards_terms', 'deleted', 1, ['modid' => $this->get_id(), 'id' => $termid]);
    }

    public static function get_all_states() {
        return self::$states;
    }

    public function get_allowed_states() {
        list($state) = $this->get_state();
        if ($state == self::STATE_END) {
            return self::$states;
        }
        return [$state];
    }

    public function get_finishedscattermsg() {
        return $this->mod->finishedscattermsg;
    }

    public function get_completedmsg() {
        return $this->mod->completedmsg;
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

    public function get_localpracticetype() {
        return $this->mod->localpracticetype;
    }

    public function get_globalpracticetype() {
        return $this->mod->globalpracticetype;
    }

    public function insert_media_urls($terms) {
        global $CFG;
        foreach($terms as $term){
            $contextid = false;
            if($term->image){
                if(!$contextid){
                    $thecm = get_coursemodule_from_instance('wordcards', $term->modid, 0, false, MUST_EXIST);
                    $contextid = context_module::instance($thecm->id)->id;
                }
                $term->image="$CFG->wwwroot/pluginfile.php/$contextid/mod_wordcards/image/$term->id";
            }
            if($term->audio){
                if(!$contextid){
                    $thecm = get_coursemodule_from_instance('wordcards', $term->modid, 0, false, MUST_EXIST);
                    $contextid = context_module::instance($thecm->id)->id;
                }
                $term->audio="$CFG->wwwroot/pluginfile.php/$contextid/mod_wordcards/audio/$term->id";
            }
        }
        return $terms;
    }

    public function get_local_terms() {
        $records = $this->get_terms();
        if (!$records) {
            return [];
        }
        shuffle($records);
        $selected_records = array_slice($records, 0, $this->mod->localtermcount);
        return $this->insert_media_urls($selected_records);
    }

    public function get_global_terms() {
        global $DB, $USER;

        $maxterms = $this->mod->globaltermcount;
        $from = 0;
        $limit = $maxterms + 5;

        // Figure out what modules are visible to the user.
        $modinfo = get_fast_modinfo($this->get_course());
        $cms = $modinfo->get_instances_of('wordcards');
        $allowedmodids = [];
        foreach ($cms as $cm) {
            if ($cm->uservisible) {
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
                     WHERE t.deleted = 0        -- The term was not deleted.
                       AND f.id $insql          -- The user has access to the module in which the term is.
                   ";
            // This is the way to make it simili random, we extract a random subset.
            $from = rand(0, $DB->count_records_sql($countsql . $sql, $params) - $maxterms - 1);
            $sql = $selectsql . $sql;
        } else {
            $sql = "SELECT t.*
                      FROM {wordcards_terms} t
                      JOIN {wordcards} f
                        ON f.id = t.modid
                      JOIN {wordcards_seen} s          -- Join on what the student has marked as seen.
                        ON s.termid = t.id
                       AND s.userid = :userid1
                 LEFT JOIN {wordcards_associations} a  -- Link the associations, if any.
                        ON a.termid = t.id
                       AND a.userid = :userid2
                     WHERE t.deleted = 0        -- The term was not deleted.
                       AND s.id IS NOT NULL     -- The user has marked the term as seen.
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
        $selected_records = array_slice($records, 0, $maxterms);

        return $this->insert_media_urls($selected_records);
    }

    public function get_state() {
        global $DB, $USER;

        // Teachers are always considered done.
        if ($this->can_manage()) {
            return [self::STATE_END, null];
        }

        $record = $DB->get_record('wordcards_progress', ['modid' => $this->get_id(), 'userid' => $USER->id]);
        if (!$record) {
            return [self::STATE_TERMS, null];
        }
        return [$record->state, json_decode($record->statedata)];
    }

    public function get_terms($includedeleted = false) {
        global $DB;
        $params = ['modid' => $this->mod->id];
        if (!$includedeleted) {
            $params['deleted'] = 0;
        }
        return $DB->get_records('wordcards_terms', $params, 'id ASC');
    }

    public function get_terms_seen() {
        global $DB, $USER;

        $sql = 'SELECT s.*
                  FROM {wordcards_seen} s
                  JOIN {wordcards_terms} t
                    ON s.termid = t.id
                   AND t.deleted = 0
                 WHERE t.modid = ?
                   AND s.userid = ?';

        return $DB->get_records_sql($sql, [$this->mod->id, $USER->id]);
    }

    protected function has_completed_local() {
        global $DB, $USER;

        $sql = "SELECT COUNT('x')
                  FROM {wordcards_terms} t
                  JOIN {wordcards_associations} a
                    ON a.termid = t.id
                 WHERE a.userid = ?
                   AND t.modid = ?
                   AND t.deleted = 0
                   AND a.successcount > 0";

        // Completed when there is enough successful associations.
        return $DB->count_records_sql($sql, [$USER->id, $this->get_id()]) >= $this->mod->localtermcount;
    }

    protected function has_seen_all_terms() {
        global $DB, $USER;

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

    public function has_user_completed_activity($userid) {
        global $DB;
        $record = $DB->get_record('wordcards_progress', ['modid' => $this->get_id(), 'userid' => $userid]);
        return $record && $record->state == self::STATE_END;
    }

    public function is_completion_enabled() {
        return !empty($this->mod->completionwhenfinish);
    }

    public function record_failed_association($term, $term2id=0) {
        global $DB, $USER;

        if ($term->modid != $this->get_id()) {
            throw new coding_exception('Invalid argument received, first term must belong to this module.');
        } else if ($term2id > 0 && !$this->course_has_term($term2id)) {
            throw new coding_exception('Unexpected association');
        }

        $params = ['userid' => $USER->id, 'termid' => $term->id];
        if (!($record1 = $DB->get_record('wordcards_associations', $params))) {
            $record1 = (object) $params;
            $record1->failcount = 0;
        }

        $record1->failcount += 1;
        $record1->lastfail = time();
        if (empty($record1->id)) {
            $DB->insert_record('wordcards_associations', $record1);
        } else {
            $DB->update_record('wordcards_associations', $record1);
        }

        if($term2id>0) {
            $params = ['userid' => $USER->id, 'termid' => $term2id];
            if (!($record2 = $DB->get_record('wordcards_associations', $params))) {
                $record2 = (object)$params;
                $record2->failcount = 0;
            }
            $record2->failcount += 1;
            $record2->lastfail = time();
            if (empty($record2->id)) {
                $DB->insert_record('wordcards_associations', $record2);
            } else {
                $DB->update_record('wordcards_associations', $record2);
            }
        }
    }

    public function record_successful_association($term) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'termid' => $term->id];
        if (!($record = $DB->get_record('wordcards_associations', $params))) {
            $record = (object) $params;
            $record->successcount = 0;
        }

        $record->successcount += 1;
        $record->lastsuccess = time();

        if (empty($record->id)) {
            $DB->insert_record('wordcards_associations', $record);
        } else {
            $DB->update_record('wordcards_associations', $record);
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

            case self::STATE_LOCAL:
                redirect(new moodle_url('/mod/wordcards/local.php', ['id' => $this->get_cmid()]));
                break;

            case self::STATE_GLOBAL:
                redirect(new moodle_url('/mod/wordcards/global.php', ['id' => $this->get_cmid()]));
                break;
        }
    }

    protected function set_state($state, $statedata = null) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'modid' => $this->get_id()];
        if ($record = $DB->get_record('wordcards_progress', $params)) {
        } else {
            $record = (object) $params;
        }

        $record->state = $state;
        $record->statedata = json_encode($statedata);
        if (!empty($record->id)) {
            $DB->update_record('wordcards_progress', $record);
        } else {
            $DB->insert_record('wordcards_progress', $record);
        }

        // The user finished the activity, notify the completion API.
        if ($state == self::STATE_END && $this->is_completion_enabled()) {
            $completion = new completion_info($this->get_course());
            if ($completion->is_enabled($this->get_cm())) {
                $completion->update_state($this->get_cm(), COMPLETION_COMPLETE);
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
            if ($this->has_seen_all_terms()) {
                $this->set_state(self::STATE_LOCAL);
                return;
            }

        } else if ($state == self::STATE_LOCAL) {
            if ($this->has_completed_local()) {
                if ($requestedstate == self::STATE_END && $this->completeafterlocal()) {
                    $this->set_state(self::STATE_END);
                    return;
                }
                $this->set_state(self::STATE_GLOBAL);
                return;
            }

        } else if ($state == self::STATE_GLOBAL) {
            // Unfortunately we do not have any other checks to perform but this one.
            if ($requestedstate == self::STATE_END) {
                $this->set_state(self::STATE_END);
                return;
            }
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
        return  has_capability('mod/wordcards:addinstance', $this->context);
    }

    public function require_manage() {
        require_capability('mod/wordcards:addinstance', $this->context);
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

    /**
     * Should we complete the wordcard when local scatter is finished.
     *
     * It is the case for a student who didn't complete any wordcard and if the
     * wordcard is set to skip the global scatter as first fashcard instance.
     *
     * @return bool true if we should complete the activity after the local scatter..
     */
    public function completeafterlocal($userid = null){
        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        // if the user has setup permission then he can never complete the wordcard.
        if ($this->can_manage()) {
            return false;
        }

        // if the activity does not support "skip global scatter on first instance" then return false.
        if (empty($this->mod->skipglobal)) {
            return false;
        }

        // Retrieve the list of wordcard modids of the course.
        $modids = array();

        foreach(get_fast_modinfo($this->course)->get_instances_of('wordcards') as $wordcard) {
            $modids[] = $wordcard->instance;
        }

        $params = array('state' => self::STATE_END, 'userid' => $userid);
        list($sqlmodidtest, $modidparams) = $DB->get_in_or_equal($modids, SQL_PARAMS_NAMED);
        $params = array_merge($params, $modidparams);
        $sqlmodidtest = 'AND modid ' . $sqlmodidtest;

        $completedwordcardtotal = $DB->count_records_select('wordcards_progress',
            'state = :state AND userid = :userid ' . $sqlmodidtest, $params);

        return (empty($completedwordcardtotal));
    }

}
