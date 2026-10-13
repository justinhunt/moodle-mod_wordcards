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

use mod_wordcards\constants;
use mod_wordcards\utils;

/**
 * Tests for reading dictionary results and refusing duplicate words.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_wordcards\local\wordwizard\word_adder
 */
final class word_adder_test extends \advanced_testcase {
    /**
     * A lookup of "bank" and of a word the dictionary does not know, as the dictionary sends it.
     *
     * @return string
     */
    protected function sample_payload(): string {
        return json_encode([
            [
                'term' => 'bank',
                'count' => 2,
                'results' => [
                    [
                        'definition' => 'an organisation where people keep their money',
                        'meaning' => 'a place for money',
                        'example' => 'I put my wages in the bank.',
                        'lang_ja' => '銀行',
                        'lang_es' => 'banco',
                        'sent_ja' => '給料を銀行に預けた。',
                    ],
                    [
                        'definition' => 'the land along the side of a river',
                        'example' => 'We sat on the bank of the river.',
                        'lang_es' => 'orilla',
                    ],
                ],
            ],
            [
                'term' => 'xyzzy',
                'count' => 0,
                'results' => [],
            ],
        ]);
    }

    public function test_read_payload_english(): void {
        $entries = word_adder::read_payload($this->sample_payload(), 'en');
        $this->assertCount(2, $entries);

        $bank = $entries[0];
        $this->assertSame('bank', $bank['term']);
        $this->assertTrue($bank['found']);
        $this->assertCount(2, $bank['senses']);

        // The short meaning wins over the long definition, and the definition is the fallback.
        $this->assertSame('a place for money', $bank['senses'][0]['definition']);
        $this->assertSame('the land along the side of a river', $bank['senses'][1]['definition']);
        $this->assertSame(1, $bank['senses'][1]['senseindex']);
        $this->assertSame('I put my wages in the bank.', $bank['senses'][0]['modelsentence']);

        $this->assertEquals(
            (object) ['ja' => '銀行', 'es' => 'banco'],
            json_decode($bank['senses'][0]['translations'])
        );
        $this->assertEquals(
            (object) ['ja' => '給料を銀行に預けた。'],
            json_decode($bank['senses'][0]['modelsentencetranslations'])
        );
        // No sentences yet is an empty object, not an empty list.
        $this->assertSame('{}', $bank['senses'][1]['modelsentencetranslations']);

        $this->assertSame('xyzzy', $entries[1]['term']);
        $this->assertFalse($entries[1]['found']);
        $this->assertSame([], $entries[1]['senses']);
    }

    public function test_read_payload_other_language(): void {
        $entries = word_adder::read_payload($this->sample_payload(), 'ja');
        $senses = $entries[0]['senses'];

        $this->assertSame('銀行', $senses[0]['definition']);
        $this->assertTrue($senses[0]['hasdefinition']);
        // The river sense has no Japanese, and must not fall back to English.
        $this->assertSame('', $senses[1]['definition']);
        $this->assertFalse($senses[1]['hasdefinition']);

        // The English definition is kept as the source definition, whatever the definitions language.
        $this->assertSame('a place for money', $senses[0]['sourcedefinition']);
        $this->assertSame('the land along the side of a river', $senses[1]['sourcedefinition']);
    }

    public function test_read_payload_unreadable(): void {
        $this->assertNull(word_adder::read_payload('unable to fetch dictionary entries', 'en'));
        $this->assertNull(word_adder::read_payload('{"term":"bank"}', 'en'));
    }

    public function test_best_sense(): void {
        $entries = word_adder::read_payload($this->sample_payload(), 'es');
        $this->assertSame('banco', word_adder::best_sense($entries[0])['definition']);

        // Only the river sense has Spanish, so it is the one to take when the first has none.
        $entry = $entries[0];
        $entry['senses'][0]['hasdefinition'] = false;
        $this->assertSame('orilla', word_adder::best_sense($entry)['definition']);

        $this->assertNull(word_adder::best_sense($entries[1]));
    }

    public function test_term_exists(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->insert_record(constants::M_TERMSTABLE, (object) ['modid' => 7, 'term' => 'Bank', 'definition' => 'x']);
        $DB->insert_record(
            constants::M_TERMSTABLE,
            (object) ['modid' => 7, 'term' => 'river', 'definition' => 'x', 'deleted' => 1]
        );

        $this->assertTrue(utils::term_exists(7, 'bank'));
        $this->assertTrue(utils::term_exists(7, ' BANK '));
        $this->assertFalse(utils::term_exists(8, 'bank'));
        // A deleted term may be added again.
        $this->assertFalse(utils::term_exists(7, 'river'));
    }
}
