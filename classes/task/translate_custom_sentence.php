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
 * Translate a term's own example sentence, where a teacher has written one.
 *
 * The companion to sync_sentence_translations, and the two are not interchangeable. That one looks
 * a sense up in the dictionary and takes the sentences it holds, which is right for a sentence the
 * dictionary gave us. This one is for a sentence the teacher wrote, where no sense holds it and a
 * lookup would come back empty however long we waited.
 *
 * It runs soon rather than tomorrow: waiting buys nothing here, because there is no dictionary task
 * to catch up with.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translate_custom_sentence extends adhoc_task {
    /**
     * A name for the admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_translate_custom_sentence', constants::M_COMPONENT);
    }

    /**
     * Translate the sentence this term now carries.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->termid)) {
            mtrace('No term id in the custom data, nothing to do.');
            return;
        }

        $term = $DB->get_record(constants::M_TERMSTABLE, ['id' => $data->termid]);
        if (!$term) {
            // Deleted between queueing this and running it.
            mtrace('Term ' . $data->termid . ' no longer exists, nothing to do.');
            return;
        }

        // The sentence may have been rewritten again, or emptied, since this was queued. Whatever
        // it says now is what gets translated, which is the point.
        if (sentence_sync::translate_custom_sentence($term)) {
            mtrace('Term ' . $term->id . ' (' . $term->term . '): sentence translated.');
        } else {
            mtrace('Term ' . $term->id . ' (' . $term->term . '): nothing usable came back.');
        }
    }

    /**
     * Ask for this term's sentence to be translated, unless it already is on the queue.
     *
     * @param int $termid
     * @param int $delay seconds to wait before running
     */
    public static function queue_for_term($termid, $delay = 0) {
        $task = new self();
        $task->set_custom_data((object)['termid' => (int)$termid]);
        if ($delay > 0) {
            $task->set_next_run_time(time() + $delay);
        }
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
