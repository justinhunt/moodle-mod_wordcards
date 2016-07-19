<?php
/**
 * Module.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Module class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_module {

    const STATE_TERMS = 'terms';
    const STATE_LOCAL = 'local';
    const STATE_GLOBAL = 'global';
    const STATE_END = 'end';

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
        $this->mod = $DB->get_record('flashcards', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = context_module::instance($cm->id);
    }

    protected function course_has_term($termid) {
        global $DB;
        $sql = "SELECT 'x'
                  FROM {flashcards_terms} t
                  JOIN {flashcards} f
                    ON t.modid = f.id
                 WHERE t.id = ?
                   AND f.course = ?
                   AND t.deleted = 0";
        return $DB->record_exists_sql($sql, [$termid, $this->get_course()->id]);
    }

    public function delete() {
        global $DB;
        $modid = $this->get_id();
        $DB->execute('DELETE FROM {flashcards_seen} s
                       WHERE s.termid IN (
                            SELECT t.id
                              FROM {flashcards_terms} t
                             WHERE t.modid = ?
                            )', [$modid]);
        $DB->delete_records('flashcards_terms', array('modid' => $modid));
        $DB->delete_records('flashcards_progress', array('modid' => $modid));
        $DB->delete_records('flashcards', array('id' => $modid));
    }

    public function delete_term($termid) {
        global $DB;
        $DB->set_field('flashcards_terms', 'deleted', 1, ['modid' => $this->get_id(), 'id' => $termid]);
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

    public function get_cm() {
        return $this->cm;
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

    public function get_local_terms() {
        $records = $this->get_terms();
        if (!$records) {
            return [];
        }
        shuffle($records);
        return array_slice($records, 0, $this->mod->localtermcount);
    }

    public function get_global_terms() {
        global $DB, $USER;

        $maxterms = $this->mod->localtermcount;

        // Figure out what modules are visible to the user.
        $modinfo = get_fast_modinfo($this->get_course());
        $cms = $modinfo->get_instances_of('flashcards');
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

        // TODO Order by relevance.
        $sql = "SELECT t.*
                  FROM {flashcards_terms} t
                  JOIN {flashcards} f
                    ON f.id = t.modid
                  JOIN {flashcards_seen} s
                    ON s.termid = t.id
                   AND s.userid = :userid1
                  JOIN {flashcards_associations} a
                    ON a.termid = t.id
                   AND a.userid = :userid2
                 WHERE t.deleted = 0        -- The term was not deleted.
                   AND s.id IS NOT NULL     -- The user has seen it the term.
                   AND f.id $insql          -- The user has access to the module in which the term is.";
        $params = [
            'userid1' => $USER->id,
            'userid2' => $USER->id,
        ];
        $params += $inparams;

        // Select a few more records to make it a bit more random.
        $records = $DB->get_records_sql($sql, $params, 0, $maxterms + 5);
        if (!$records) {
            return [];
        }
        shuffle($records);
        return array_slice($records, 0, $maxterms);
    }

    public function get_state() {
        global $DB, $USER;

        // Teachers are always considered done.
        if ($this->can_manage()) {
            return [self::STATE_END, null];
        }

        $record = $DB->get_record('flashcards_progress', ['modid' => $this->get_id(), 'userid' => $USER->id]);
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
        return $DB->get_records('flashcards_terms', $params, 'id ASC');
    }

    public function get_terms_seen() {
        global $DB, $USER;

        $sql = 'SELECT s.*
                  FROM {flashcards_seen} s
                  JOIN {flashcards_terms} t
                    ON s.termid = t.id
                   AND t.deleted = 0
                 WHERE t.modid = ?
                   AND s.userid = ?';

        return $DB->get_records_sql($sql, [$this->mod->id, $USER->id]);
    }

    public function has_completed_local() {
        global $DB, $USER;

        $sql = "SELECT COUNT('x')
                  FROM {flashcards_terms} t
                  JOIN {flashcards_associations} a
                    ON a.termid = t.id
                 WHERE a.userid = ?
                   AND t.modid = ?
                   AND t.deleted = 0
                   AND a.successcount > 0";

        // Completed when there is enough successful associations.
        return $DB->count_records_sql($sql, [$USER->id, $this->get_id()]) >= $this->mod->localtermcount;
    }

    public function has_seen_all_terms() {
        global $DB, $USER;

        if (!$this->has_terms()) {
            return false;
        }

        $sql = "SELECT 'x'
                  FROM {flashcards_terms} t
             LEFT JOIN {flashcards_seen} s
                    ON t.id = s.termid
                   AND s.userid = ?
                 WHERE t.deleted = 0
                   AND s.id IS NULL";

        // We've seen it all when there is no null entries.
        return !$DB->record_exists_sql($sql, [$USER->id]);
    }

    public function has_terms() {
        global $DB;
        return $DB->record_exists('flashcards_terms', ['modid' => $this->get_id()]);
    }

    public function record_failed_association($term, $term2id) {
        global $DB, $USER;

        if ($term->modid != $this->get_id()) {
            throw new coding_exception('Invalid argument received, first term must belong to this module.');
        } else if (!$this->course_has_term($term2id)) {
            throw new coding_exception('Unexpected association');
        }

        $params = ['userid' => $USER->id, 'termid' => $term->id];
        if (!($record1 = $DB->get_record('flashcards_associations', $params))) {
            $record1 = (object) $params;
            $record1->failcount = 0;
        }

        $params = ['userid' => $USER->id, 'termid' => $term2id];
        if (!($record2 = $DB->get_record('flashcards_associations', $params))) {
            $record2 = (object) $params;
            $record2->failcount = 0;
        }

        $record1->failcount += 1;
        $record2->failcount += 1;
        $record1->lastfail = time();
        $record2->lastfail = time();

        if (empty($record1->id)) {
            $DB->insert_record('flashcards_associations', $record1);
        } else {
            $DB->update_record('flashcards_associations', $record1);
        }
        if (empty($record2->id)) {
            $DB->insert_record('flashcards_associations', $record2);
        } else {
            $DB->update_record('flashcards_associations', $record2);
        }
    }

    public function record_successful_association($term) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'termid' => $term->id];
        if (!($record = $DB->get_record('flashcards_associations', $params))) {
            $record = (object) $params;
            $record->successcount = 0;
        }

        $record->successcount += 1;
        $record->lastsuccess = time();

        if (empty($record->id)) {
            $DB->insert_record('flashcards_associations', $record);
        } else {
            $DB->update_record('flashcards_associations', $record);
        }
    }

    public function resume_progress($currentstate) {
        $this->update_state();
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
                redirect(new moodle_url('/mod/flashcards/view.php', ['id' => $this->get_cmid()]));
                break;

            case self::STATE_LOCAL:
                redirect(new moodle_url('/mod/flashcards/local.php', ['id' => $this->get_cmid()]));
                break;

            case self::STATE_GLOBAL:
                redirect(new moodle_url('/mod/flashcards/global.php', ['id' => $this->get_cmid()]));
                break;
        }
    }

    protected function set_state($state, $statedata = null) {
        global $DB, $USER;

        $params = ['userid' => $USER->id, 'modid' => $this->get_id()];
        if ($record = $DB->get_record('flashcards_progress', $params)) {
        } else {
            $record = (object) $params;
        }

        $record->state = $state;
        $record->statedata = json_encode($statedata);
        if (!empty($record->id)) {
            $DB->update_record('flashcards_progress', $record);
        } else {
            $DB->insert_record('flashcards_progress', $record);
        }
    }

    protected function update_state() {
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
                $this->set_state(self::STATE_GLOBAL);
                return;
            }

        } else if ($state == self::STATE_GLOBAL) {

        }
    }

    // Factories.
    public static function get_by_cmid($cmid) {
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'flashcards');
        return new static($course, $cm);
    }

    public static function get_by_modid($modid) {
        list($course, $cm) = get_course_and_cm_from_instance($modid, 'flashcards');
        return new static($course, $cm);
    }

    // Capabilities.
    public function can_manage() {
        return  has_capability('mod/flashcards:addinstance', $this->context);
    }

    public function require_manage() {
        require_capability('mod/flashcards:addinstance', $this->context);
    }

    public function can_view() {
        return  has_capability('mod/flashcards:view', $this->context);
    }

    public function require_view() {
        self::require_view_in_context($this->context);
    }

    public static function require_view_in_context(context $context) {
        require_capability('mod/flashcards:view', $context);
    }

}
