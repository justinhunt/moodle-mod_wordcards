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

namespace mod_wordcards;

/**
 * Access checks on saving a wordcards activity's terms.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_wordcards_external::submit_mform
 * @covers     ::wordcards_output_fragment_mform
 */
final class term_access_test extends \advanced_testcase {
    /** @var \stdClass the activity */
    protected $wordcards;

    /** @var \stdClass an activity in another course */
    protected $otherwordcards;

    /** @var \stdClass a student in the first course */
    protected $student;

    /** @var \stdClass an editing teacher in the first course */
    protected $teacher;

    /**
     * Load the library files.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/wordcards/lib.php');
    }

    /**
     * Two courses with an activity each, a student and a teacher in the first.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $this->wordcards = $gen->create_module('wordcards', ['course' => $course->id]);
        $this->otherwordcards = $gen->create_module('wordcards', ['course' => $gen->create_course()->id]);
        $this->student = $gen->create_and_enrol($course, 'student');
        $this->teacher = $gen->create_and_enrol($course, 'editingteacher');
    }

    /**
     * Add a term to an activity.
     *
     * @param \stdClass $wordcards the activity
     * @return \stdClass the term
     */
    protected function create_term(\stdClass $wordcards): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_wordcards')
            ->create_term($wordcards->id, ['term' => 'original', 'definition' => 'original']);
    }

    /**
     * Save a term through the web service, as the current user.
     *
     * @param int $termid the term id, or 0 for a new term
     * @return array the web service result
     */
    protected function submit_term(int $termid): array {
        $formdata = http_build_query([
            'termid' => $termid,
            'term' => 'changed',
            'definition' => 'changed',
            'model_sentence' => '',
            'sesskey' => sesskey(),
            '_qf__mod_wordcards_form_term' => 1,
        ], '', '&');
        $contextid = \context_module::instance($this->wordcards->cmid)->id;
        // The form checks the request's sesskey, which a real AJAX call sends in its URL.
        $_POST['sesskey'] = sesskey();
        return (array) json_decode(\mod_wordcards_external::submit_mform($contextid, json_encode($formdata)));
    }

    /**
     * A student cannot save a term.
     */
    public function test_submit_mform_denied_for_student(): void {
        $term = $this->create_term($this->wordcards);
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        $this->submit_term($term->id);
    }

    /**
     * A teacher cannot save another activity's term through their own activity.
     */
    public function test_submit_mform_other_activity_term_refused(): void {
        global $DB;
        $otherterm = $this->create_term($this->otherwordcards);
        $this->setUser($this->teacher);
        try {
            $this->submit_term($otherterm->id);
            $this->fail('A term of another activity was saved');
        } catch (\moodle_exception $e) {
            $this->assertEquals('original', $DB->get_field('wordcards_terms', 'term', ['id' => $otherterm->id]));
        }
    }

    /**
     * A teacher can still save their own activity's term.
     */
    public function test_submit_mform_allowed_for_teacher(): void {
        global $DB;
        $term = $this->create_term($this->wordcards);
        $this->setUser($this->teacher);
        $this->submit_term($term->id);
        $this->assertEquals('changed', $DB->get_field('wordcards_terms', 'term', ['id' => $term->id]));
    }

    /**
     * A student cannot load the term form fragment.
     */
    public function test_mform_fragment_denied_for_student(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        wordcards_output_fragment_mform([
            'context' => \context_module::instance($this->wordcards->cmid),
            'type' => 'term',
            'itemid' => 0,
        ]);
    }
}
