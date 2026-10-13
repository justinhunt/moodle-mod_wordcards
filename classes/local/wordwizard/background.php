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

namespace mod_wordcards\local\wordwizard;

use mod_wordcards\task\add_word;
use mod_wordcards\task\generate_word_images;
use mod_wordcards\utils;

/**
 * Adds words to a word set in the background, through adhoc tasks.
 *
 * A dictionary lookup can take a while and now and then times out, and a teacher waiting on a
 * page for twenty of them sees both. In the background each word is a task of its own, so the
 * lookups run side by side as far as cron allows, and one that fails is tried again on its own.
 *
 * The pictures for a batch are one more task, run once the words are in. Making them in one go
 * sends the requests out together, where a task per word would make them a few at a time.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class background {
    /** @var int the most words one request may queue */
    const MAX_WORDS = 200;

    /** @var int how often a word's lookup is tried before it is given up on */
    const ATTEMPTS = 5;

    /**
     * Queue words to be looked up and added, the first good meaning of each.
     *
     * @param \mod_wordcards_module $mod
     * @param array $words
     * @param string $deflang the language the definitions should be in
     * @param bool $withimages also make a picture for each word
     * @return array queued (int) and alreadyinset (list of words left out because they are there already)
     */
    public static function queue_words(\mod_wordcards_module $mod, array $words, $deflang, $withimages) {
        $modid = $mod->get_id();
        $sourcelang = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);

        $queued = [];
        $alreadyinset = [];
        foreach (self::clean_words($words) as $word) {
            if (utils::term_exists($modid, $word)) {
                $alreadyinset[] = $word;
                continue;
            }
            $task = new add_word();
            // The module id goes first, pending() finds a module's tasks by it.
            $task->set_custom_data((object) [
                'modid' => (int) $modid,
                'word' => $word,
                'sourcelang' => $sourcelang,
                'deflang' => $deflang,
            ]);
            self::limit_attempts($task);
            \core\task\manager::queue_adhoc_task($task);
            $queued[] = $word;
        }

        if ($withimages && $queued) {
            generate_word_images::queue($modid, $queued);
        }

        return ['queued' => count($queued), 'alreadyinset' => $alreadyinset];
    }

    /**
     * Words as typed: trimmed, without blanks or repeats, and no more than MAX_WORDS of them.
     *
     * @param array $words
     * @return array
     */
    public static function clean_words(array $words) {
        $clean = [];
        foreach ($words as $word) {
            $word = trim($word);
            $key = \core_text::strtolower($word);
            if ($word !== '' && !isset($clean[$key])) {
                $clean[$key] = $word;
            }
        }
        return array_slice(array_values($clean), 0, self::MAX_WORDS);
    }

    /**
     * Do not let a failing task retry for days. Moodle 4.4 and later only.
     *
     * Its retries wait 1, 2, 4 and 8 minutes, so a word that cannot be looked up is given up on
     * a quarter of an hour later rather than retried for days, each time at the site's expense.
     *
     * @param \core\task\adhoc_task $task
     */
    public static function limit_attempts(\core\task\adhoc_task $task) {
        if (method_exists($task, 'set_attempts_available')) {
            $task->set_attempts_available(self::ATTEMPTS);
        }
    }

    /**
     * What is still to be done in the background for a module.
     *
     * @param int $modid
     * @return array words and images (tasks waiting to run), and retrying (word lookups that failed
     *               and will be tried again)
     */
    public static function pending($modid) {
        return [
            'words' => self::count_tasks(add_word::class, $modid),
            'images' => self::count_tasks(generate_word_images::class, $modid),
            'retrying' => self::count_tasks(add_word::class, $modid, true),
        ];
    }

    /**
     * Count a module's tasks of one kind that are still to run.
     *
     * @param string $classname
     * @param int $modid
     * @param bool $failedonly only those that have failed at least once
     * @return int
     */
    protected static function count_tasks($classname, $modid, $failedonly = false) {
        global $DB;

        $select = 'classname = :classname AND ' . $DB->sql_like('customdata', ':customdata');
        $params = [
            'classname' => \core\task\manager::get_canonical_class_name($classname),
            'customdata' => '{"modid":' . (int) $modid . ',%',
        ];
        // A task that has run out of attempts stays in the table, but will not run again.
        if (method_exists(\core\task\adhoc_task::class, 'set_attempts_available')) {
            $select .= ' AND (attemptsavailable > 0 OR attemptsavailable IS NULL)';
        }
        if ($failedonly) {
            $select .= ' AND faildelay > 0';
        }
        return $DB->count_records_select('task_adhoc', $select, $params);
    }
}
