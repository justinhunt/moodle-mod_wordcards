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

namespace mod_wordcards\task;

use core\task\adhoc_task;
use mod_wordcards\constants;
use mod_wordcards\local\ai\completer;
use mod_wordcards\local\ai\translator;
use mod_wordcards\local\wordwizard\background;

/**
 * Fill in, with the site's AI, the translations one term's dictionary entry did not have.
 *
 * It runs as the teacher who added the word, because the site's AI is used on somebody's behalf:
 * it is their acceptance of the AI policy and their capability that count, and their use the site
 * logs. A term that cannot be completed is not retried - the AI declining once will usually
 * decline again, at the site's expense each time.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class complete_translations extends adhoc_task {
    /**
     * A name for the admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_complete_translations', constants::M_COMPONENT);
    }

    /**
     * Complete the term.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->termid)) {
            mtrace('No term id in the custom data, nothing to do.');
            return;
        }
        $term = $DB->get_record(constants::M_TERMSTABLE, ['id' => $data->termid, 'deleted' => 0]);
        if (!$term) {
            // Deleted between queueing this and running it.
            mtrace('Term ' . $data->termid . ' no longer exists, nothing to do.');
            return;
        }

        // Things may have changed since this was queued: the site's AI switched off, the policy
        // withdrawn, the teacher's role removed.
        $mod = \mod_wordcards_module::get_by_modid($term->modid);
        if (!translator::is_available($mod->get_context(), $this->get_userid())) {
            mtrace('Term ' . $term->id . ': the site AI cannot be used for this user here now, not completed.');
            return;
        }

        $translator = new translator($mod->get_context(), $this->get_userid());
        $result = completer::complete_term($term, !empty($data->customsentence), $translator);
        mtrace('Term ' . $term->id . ' (' . $term->term . '): '
            . ($result['sentencemade'] ? 'example sentence made, ' : '')
            . $result['words'] . ' word(s) and ' . $result['sentences'] . ' sentence(s) added.'
            . ($result['message'] !== '' ? ' ' . $result['message'] . '.' : ''));
    }

    /**
     * Ask for a term to be completed, unless it already is on the queue.
     *
     * @param int $termid
     * @param bool $customsentence the example sentence is the teacher's own, not the dictionary's
     * @param int $userid the user the AI is used on behalf of
     */
    public static function queue_for_term($termid, $customsentence, $userid) {
        $task = new self();
        $task->set_custom_data((object) ['termid' => (int) $termid, 'customsentence' => (bool) $customsentence]);
        $task->set_userid($userid);
        background::limit_attempts($task);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
