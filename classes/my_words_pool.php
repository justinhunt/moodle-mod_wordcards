<?php
/**
 * Class to handle "My Words" word pool.
 *
 * @package mod_wordcards
 * @author  David Watson - evolutioncode.uk
 */

namespace mod_wordcards;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to handle "My Words" word pool.
 *
 * @package mod_wordcards
 * @author  David Watson - evolutioncode.uk
 */
class my_words_pool {

    private $courseid;
    private $pool;

    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    public function get_words() {
        global $DB, $USER;
        if ($this->pool == null) {
            $this->pool = $DB->get_records_sql(
            "SELECT t.*
                FROM {wordcards_terms} t
                JOIN {wordcards_my_words} m ON m.termid = t.id AND m.courseid = ? AND m.userid = ?
                ORDER BY t.id",
                [$this->courseid, $USER->id]
            );
            if(!empty($this->pool)) {
                $this->pool = \mod_wordcards_module::insert_media_urls($this->pool);
            }
        }
        return $this->pool;
    }

    public function has_term(int $id) {
        return isset($this->get_words()[$id]);
    }

    public function add_word(int $termid): bool {
        global $DB, $USER;
        if ($this->has_term($termid)) {
           throw new \invalid_parameter_exception('Pool already has word id ' . $termid);
        }
        // Validate term exists.
        if (!$DB->record_exists('wordcards_terms', ['id' => $termid])) {
            throw new \invalid_parameter_exception('Invalid term id ' . $termid);
        }
        return (bool) $DB->insert_record(
            'wordcards_my_words',
            (object)[
                'userid' => $USER->id,
                'termid' => $termid,
                'courseid' => $this->courseid,
                'timemodified' => time()
            ]
        );
    }

    public function remove_word(int $termid): bool {
        global $DB, $USER;
        if (!$this->has_term($termid)) {
            throw new \invalid_parameter_exception('Pool does not have word ' . $termid . ' to remove');
        }
        return $DB->delete_records(
            'wordcards_my_words',
            [
                'userid' => $USER->id,
                'termid' => $termid,
                'courseid' => $this->courseid
            ]
        );
    }
}