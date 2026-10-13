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
use mod_wordcards\local\wordwizard\word_adder;
use mod_wordcards\utils;

/**
 * Look a word up in the dictionary and add its first good meaning to a word set.
 *
 * Queued by \mod_wordcards\local\wordwizard\background. A lookup that fails throws, so that cron
 * tries it again later. Anything else (the word is not in the dictionary, or is in the set
 * already) is an answer, and the task ends there.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_word extends adhoc_task {
    /**
     * A name for the admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_add_word', constants::M_COMPONENT);
    }

    /**
     * Look the word up and add it.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->modid) || !isset($data->word)) {
            mtrace('No module or word in the custom data, nothing to do.');
            return;
        }
        if (!$DB->record_exists(constants::M_TABLE, ['id' => $data->modid])) {
            // Deleted between queueing this and running it.
            mtrace('Wordcards ' . $data->modid . ' no longer exists, nothing to do.');
            return;
        }

        // Asked for twice, or added by hand in the meantime. A retry of this task that added the
        // word before failing ends here too, which is what keeps retries from adding it twice.
        if (utils::term_exists($data->modid, $data->word)) {
            mtrace('"' . $data->word . '" is already in wordcards ' . $data->modid . '.');
            return;
        }

        // Without credentials no lookup will ever work, so do not retry.
        $conf = get_config(constants::M_COMPONENT);
        if (empty($conf->apiuser) || empty($conf->apisecret)) {
            mtrace('No Poodll credentials are set, so "' . $data->word . '" cannot be looked up.');
            return;
        }

        $entries = word_adder::lookup($data->word, $data->sourcelang, $data->deflang, $error);
        if ($entries === null) {
            mtrace('"' . $data->word . '": the lookup failed. ' . $error);
            throw new \moodle_exception('ww_lookupfailed', constants::M_COMPONENT);
        }

        $entry = reset($entries);
        $sense = $entry ? word_adder::best_sense($entry) : null;
        if (!$sense) {
            mtrace('"' . $data->word . '": no meaning with a definition in ' . $data->deflang . ', not added.');
            return;
        }

        if (word_adder::add_sense($data->modid, $data->word, $sense)) {
            mtrace('"' . $data->word . '" added to wordcards ' . $data->modid . ': ' . $sense['definition']);
        } else {
            mtrace('"' . $data->word . '" was not added to wordcards ' . $data->modid . '.');
        }
    }
}
