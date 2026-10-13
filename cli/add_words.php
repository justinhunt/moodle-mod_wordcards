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

/**
 * Look words up in the dictionary and add them to a wordcards activity, from the command line.
 *
 * The Word Wizard's lookup without the page. The first meaning of each word that has a definition
 * in the definitions language is added, as when the wizard adds words in the background.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_wordcards\constants;
use mod_wordcards\imagegen;
use mod_wordcards\local\wordwizard\background;
use mod_wordcards\local\wordwizard\word_adder;
use mod_wordcards\utils;

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'cmid' => 0,
        'modid' => 0,
        'words' => '',
        'file' => '',
        'deflang' => '',
        'images' => false,
        'queue' => false,
        'dryrun' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if (
    $options['help'] || (empty($options['cmid']) && empty($options['modid'])) ||
    ($options['words'] === '' && $options['file'] === '')
) {
    echo "
Look words up in the dictionary and add them to a wordcards activity.

The first meaning of each word that has a definition in the definitions language is added. Words
already in the activity are left out.

Options:
  -h, --help     Print this help.
      --cmid     The activity's course module id (the id in its URL). This or --modid is required.
      --modid    The wordcards instance id.
      --words    The words, separated by commas. This or --file is required.
      --file     A text file of words, one per line (commas work too).
      --deflang  The definitions language, e.g. ja. Defaults to the activity's own.
      --images   Also make a picture for each word added.
      --queue    Queue the words to be added by cron, as the Word Wizard's background option
                 does, rather than adding them now.
      --dryrun   Look the words up and show the meaning that would be added, without adding it.

Examples:
  php mod/wordcards/cli/add_words.php --cmid=12 --words=\"apple,banana,cherry\" --dryrun
  php mod/wordcards/cli/add_words.php --cmid=12 --file=/tmp/words.txt --deflang=ja --images
  php mod/wordcards/cli/add_words.php --modid=3 --file=/tmp/words.txt --queue
";
    die;
}

if (!empty($options['cmid'])) {
    $mod = mod_wordcards_module::get_by_cmid((int) $options['cmid']);
} else {
    if (!$DB->record_exists(constants::M_TABLE, ['id' => (int) $options['modid']])) {
        cli_error('No wordcards instance with id ' . (int) $options['modid']);
    }
    $mod = mod_wordcards_module::get_by_modid((int) $options['modid']);
}
$modid = $mod->get_id();

// The words.
$text = $options['words'];
if ($options['file'] !== '') {
    if (!is_readable($options['file'])) {
        cli_error('Cannot read ' . $options['file']);
    }
    $text = file_get_contents($options['file']);
}
$words = background::clean_words(preg_split('/[,\r\n]+/', $text));
if (!$words) {
    cli_error('No words given.');
}

// The definitions language: one a lookup can actually return, as the Word Wizard offers.
$sourcelang = utils::fetch_rcdic_lang($mod->get_mod()->ttslanguage);
$deflang = $options['deflang'] !== '' ? $options['deflang'] : $mod->get_mod()->deflanguage;
$allowed = utils::get_lookup_deflangs($sourcelang);
if (!in_array($deflang, $allowed)) {
    cli_error('The dictionary cannot give ' . $sourcelang . ' definitions in "' . $deflang . '". Use --deflang with one of: ' .
        implode(', ', $allowed));
}

cli_writeln('Activity ' . $modid . ': ' . format_string($mod->get_mod()->name) . ' (' . $sourcelang . ' words, ' .
    $deflang . ' definitions)');

if (!empty($options['queue'])) {
    if (!empty($options['dryrun'])) {
        cli_error('--queue and --dryrun cannot be used together.');
    }
    $result = background::queue_words($mod, $words, $deflang, !empty($options['images']));
    cli_writeln($result['queued'] . ' word(s) queued, to be added when cron runs.');
    if ($result['alreadyinset']) {
        cli_writeln('Already in the activity: ' . implode(', ', $result['alreadyinset']));
    }
    exit(0);
}

if (!empty($options['dryrun'])) {
    cli_writeln('DRY RUN. The words are looked up but nothing is added.');
}

$counts = ['added' => 0, 'already' => 0, 'notfound' => 0, 'failed' => 0];
$addedids = [];
foreach ($words as $word) {
    if (utils::term_exists($modid, $word)) {
        cli_writeln('  = ' . $word . ': already in the activity');
        $counts['already']++;
        continue;
    }
    $entries = word_adder::lookup($word, $sourcelang, $deflang, $error);
    if ($entries === null) {
        cli_writeln('  ! ' . $word . ': the lookup failed. ' . $error);
        $counts['failed']++;
        continue;
    }
    $entry = reset($entries);
    $sense = $entry ? word_adder::best_sense($entry) : null;
    if (!$sense) {
        cli_writeln('  - ' . $word . ': no meaning with a ' . $deflang . ' definition');
        $counts['notfound']++;
        continue;
    }
    if (!empty($options['dryrun'])) {
        cli_writeln('  + ' . $word . ': ' . $sense['definition']);
        $counts['added']++;
        continue;
    }
    $termid = word_adder::add_sense($modid, $word, $sense);
    if ($termid) {
        cli_writeln('  + ' . $word . ': ' . $sense['definition']);
        $addedids[] = $termid;
        $counts['added']++;
    } else {
        cli_writeln('  ! ' . $word . ': could not be added');
        $counts['failed']++;
    }
}

cli_writeln('');
cli_writeln(($options['dryrun'] ? 'Would add ' : 'Added ') . $counts['added'] . ', already there ' . $counts['already'] .
    ', no meaning found ' . $counts['notfound'] . ', failed ' . $counts['failed'] . '.');
if ($counts['failed']) {
    cli_writeln('Failed lookups can simply be run again: words already added are left out.');
}

if (!empty($options['images']) && $addedids) {
    cli_writeln('Making pictures for ' . count($addedids) . ' word(s)...');
    [$insql, $params] = $DB->get_in_or_equal($addedids);
    $terms = $DB->get_records_select(constants::M_TERMSTABLE, 'id ' . $insql, $params);
    $made = (new imagegen($mod))->generate_for_terms($terms);
    cli_writeln(count($made) . ' picture(s) made.');
}
