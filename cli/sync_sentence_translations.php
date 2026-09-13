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

/**
 * Fetch the translated example sentences for terms that do not have them.
 *
 * One of several ways to call \mod_wordcards\sentence_sync. The work is in that class, so a button
 * or a scheduled task can ask for the same thing without repeating any of it.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_wordcards\constants;
use mod_wordcards\sentence_sync;

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'modid' => 0,
        'all' => false,
        'refresh' => false,
        'limit' => 0,
        'dryrun' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || (empty($options['modid']) && empty($options['all']))) {
    echo "
Fetch the translated example sentences for wordcards terms that do not have them.

A term only gets these from the dictionary if the dictionary had generated them by the time the
term was looked up, so terms made earlier have none. This fills them in afterwards.

Options:
  -h, --help     Print this help.
      --modid    The wordcards instance to do. Required unless --all is given.
      --all      Every wordcards instance on the site.
      --refresh  Also re-fetch terms that already have translations stored.
      --limit    Stop after this many dictionary lookups per activity. 0 means no limit.
      --dryrun   Report what would happen and write nothing.

Examples:
  php mod/wordcards/cli/sync_sentence_translations.php --modid=3 --dryrun
  php mod/wordcards/cli/sync_sentence_translations.php --modid=3 --limit=10
  php mod/wordcards/cli/sync_sentence_translations.php --all
";
    die;
}

if (!empty($options['all'])) {
    $mods = $DB->get_records(constants::M_TABLE, null, 'id ASC', 'id, name');
} else {
    $mods = $DB->get_records(constants::M_TABLE, ['id' => (int)$options['modid']], '', 'id, name');
    if (!$mods) {
        cli_error('No wordcards instance with id ' . (int)$options['modid']);
    }
}

if (!empty($options['dryrun'])) {
    cli_writeln('DRY RUN. Dictionary lookups still happen, but nothing is written.');
}

$totals = [];
foreach ($mods as $mod) {
    cli_writeln('');
    cli_writeln('Activity ' . $mod->id . ': ' . $mod->name);
    $counts = sentence_sync::sync_module(
        $mod->id,
        !empty($options['refresh']),
        (int)$options['limit'],
        !empty($options['dryrun'])
    );

    // Terms nothing matched have a sentence of their own, which no lookup will ever find. Queue
    // those to be translated directly, as the Word Wizard's button does.
    $unmatchedids = $counts['unmatchedids'];
    unset($counts['unmatchedids']);
    $counts['queued'] = 0;
    if (!empty($options['dryrun'])) {
        $counts['queued'] = count($unmatchedids);
    } else {
        foreach ($unmatchedids as $termid) {
            \mod_wordcards\task\translate_custom_sentence::queue_for_term($termid);
            $counts['queued']++;
        }
    }

    foreach ($counts as $key => $value) {
        $totals[$key] = (isset($totals[$key]) ? $totals[$key] : 0) + $value;
        cli_writeln('  ' . str_pad($key, 14) . $value);
    }
}

cli_writeln('');
cli_writeln(str_repeat('-', 50));
cli_writeln('Totals across ' . count($mods) . ' activity(s)');
foreach ($totals as $key => $value) {
    cli_writeln('  ' . str_pad($key, 14) . $value);
}
cli_writeln('');
cli_writeln('  updated    = the sentence translations are now stored');
cli_writeln('  unmatched  = no sense had this term\'s example sentence, usually because it was edited');
cli_writeln('  queued     = those same terms, queued to have their own sentence translated instead');
cli_writeln('  nodata     = the sense matched but the dictionary has no translated sentences for it yet');
