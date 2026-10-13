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
use mod_wordcards\imagegen;
use mod_wordcards\local\wordwizard\background;

/**
 * Make a picture for each of a batch of words added in the background, or for every word in a
 * word set that has none (the Word Wizard's bulk image button, in the background).
 *
 * It waits for the words to be in first. While any of the module's add_word tasks are still to
 * run it queues itself again a minute later, up to MAX_WAITS times, and then makes pictures for
 * whichever of the words have arrived.
 *
 * It does not retry. A picture that fails costs as much as one that works, and the Word Wizard's
 * bulk image button is there to fill any gaps.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_word_images extends adhoc_task {
    /** @var int seconds to wait between checks that the words are in */
    const WAIT = 60;

    /** @var int how many times to wait before going ahead with the words there are */
    const MAX_WAITS = 30;

    /**
     * Queue pictures for a batch of words.
     *
     * @param int $modid
     * @param array $words
     */
    public static function queue($modid, array $words) {
        $task = new self();
        // The module id goes first, background::pending() finds a module's tasks by it.
        $task->set_custom_data((object) [
            'modid' => (int) $modid,
            'words' => array_values($words),
            'waits' => 0,
        ]);
        $task->set_next_run_time(time() + self::WAIT);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Queue pictures for every word in a module that has none.
     *
     * Queued once: pressing the button again while it waits does not queue it twice.
     *
     * @param int $modid
     */
    public static function queue_all($modid) {
        $task = new self();
        $task->set_custom_data((object) [
            'modid' => (int) $modid,
            'all' => true,
            'waits' => 0,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Run once, see the class comment. Moodle 4.4 and later; before that a failed task retries.
     *
     * @return bool
     */
    public function retry_until_success(): bool {
        return false;
    }

    /**
     * A name for the admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_generate_word_images', constants::M_COMPONENT);
    }

    /**
     * Make the pictures, once the words are in.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $all = !empty($data->all);
        if (empty($data->modid) || (!$all && empty($data->words))) {
            mtrace('No module or words in the custom data, nothing to do.');
            return;
        }
        if (!$DB->record_exists(constants::M_TABLE, ['id' => $data->modid])) {
            mtrace('Wordcards ' . $data->modid . ' no longer exists, nothing to do.');
            return;
        }

        // Words still being added would miss out, so wait for them. The bulk button means them too.
        $waiting = background::pending($data->modid)['words'];
        if ($waiting > 0 && $data->waits < self::MAX_WAITS) {
            mtrace($waiting . ' word(s) still to be added to wordcards ' . $data->modid . ', checking again shortly.');
            $data->waits++;
            $again = new self();
            $again->set_custom_data($data);
            $again->set_next_run_time(time() + self::WAIT);
            \core\task\manager::queue_adhoc_task($again);
            return;
        }

        $mod = \mod_wordcards_module::get_by_modid($data->modid);
        $terms = $mod->get_term_records();
        if (!$all) {
            $wanted = array_map('core_text::strtolower', $data->words);
            $terms = array_filter($terms, function ($term) use ($wanted) {
                return in_array(\core_text::strtolower(trim($term->term)), $wanted);
            });
        }
        if (!$terms) {
            mtrace('None of the words are in wordcards ' . $data->modid . ', no pictures to make.');
            return;
        }

        $imagegen = new imagegen($mod);
        try {
            $made = $imagegen->generate_for_terms($terms);
        } catch (\Throwable $e) {
            // Caught rather than thrown, so that no Moodle version retries it.
            mtrace('Making pictures for wordcards ' . $data->modid . ' failed: ' . $e->getMessage());
            return;
        }
        mtrace(count($made) . ' picture(s) made for ' . count($terms) . ' word(s) in wordcards ' . $data->modid . '.');
    }
}
