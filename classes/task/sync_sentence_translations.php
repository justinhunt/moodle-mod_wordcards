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

namespace mod_wordcards\task;

use core\task\adhoc_task;
use mod_wordcards\constants;
use mod_wordcards\sentence_sync;

defined('MOODLE_INTERNAL') || die();

/**
 * Fetch the translated example sentences a term went without when it was added.
 *
 * The dictionary generates these in a task of its own, so a word looked up today usually has none
 * yet. Rather than leave the card short for good, adding such a term queues this to come back a day
 * later, by which time the dictionary has generally caught up.
 *
 * It runs once. If the sentences are still not there, the terms keep whatever they have and the
 * Word Wizard's sync button remains for anyone who wants to try again by hand - a task that
 * rescheduled itself would go round for ever on a sense the dictionary will never fill.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_sentence_translations extends adhoc_task {
    /**
     * A name for the admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_sync_sentence_translations', constants::M_COMPONENT);
    }

    /**
     * Fetch whatever the dictionary has for this activity now.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->modid)) {
            mtrace('No activity id in the custom data, nothing to do.');
            return;
        }

        if (!$DB->record_exists(constants::M_TABLE, ['id' => $data->modid])) {
            // The activity was deleted between queueing this and running it.
            mtrace('Activity ' . $data->modid . ' no longer exists, nothing to do.');
            return;
        }

        $counts = sentence_sync::sync_module($data->modid);

        // Terms nothing matched carry a sentence of their own, which no lookup will find however
        // often this runs. Hand those to the task that can translate one, as the button and the
        // cli script both do.
        foreach ($counts['unmatchedids'] as $termid) {
            translate_custom_sentence::queue_for_term($termid);
        }

        mtrace('Activity ' . $data->modid . ': ' . $counts['updated'] . ' term(s) updated, '
            . $counts['nodata'] . ' still had nothing at the dictionary, '
            . count($counts['unmatchedids']) . ' queued for translating on their own.');
    }

    /**
     * Ask for this activity to be revisited, unless it already is on the queue.
     *
     * Adding a dozen words one after another should leave one task, not a dozen, so the custom data
     * carries nothing but the activity id and queue_adhoc_task is asked to check for a duplicate.
     *
     * @param int $modid the wordcards instance id
     * @param int $delay seconds to wait before running, a day by default
     */
    public static function queue_for_module($modid, $delay = DAYSECS) {
        $task = new self();
        $task->set_custom_data((object)['modid' => (int)$modid]);
        $task->set_next_run_time(time() + $delay);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
