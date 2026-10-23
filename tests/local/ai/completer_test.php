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

namespace mod_wordcards\local\ai;

use mod_wordcards\constants;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for filling in a term's missing translations.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(completer::class)]
#[CoversClass(translator::class)]
final class completer_test extends \advanced_testcase {
    /**
     * A Finnish activity defining in English, with the language chooser on.
     *
     * @return array the module and a translator for it
     */
    protected function setup_activity(): array {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('wordcards', [
            'course' => $course->id,
            'ttslanguage' => 'fi-FI',
            'deflanguage' => 'en',
            'showlangchooser' => 1,
        ]);
        $mod = \mod_wordcards_module::get_by_modid($instance->id);
        return [$mod, $this->fake_translator($mod->get_context())];
    }

    /**
     * A translator that answers from a script instead of asking an AI.
     *
     * Set sentencereply and translatereply to what the AI should say; prompts collects what it
     * was asked.
     *
     * @param \context $context
     * @return translator
     */
    protected function fake_translator(\context $context): translator {
        return new class ($context, 2) extends translator {
            /** @var array|null the reply to a request for a sentence */
            public $sentencereply = null;

            /** @var array|null the reply to a request for translations */
            public $translatereply = null;

            /** @var array the prompts that were sent */
            public $prompts = [];

            #[\Override]
            protected function ask($prompt) {
                $this->prompts[] = $prompt;
                return strpos($prompt, '{"sentence":') !== false ? $this->sentencereply : $this->translatereply;
            }
        };
    }

    /**
     * Add a term to the activity.
     *
     * @param \mod_wordcards_module $mod
     * @param array $fields
     * @return \stdClass
     */
    protected function create_term(\mod_wordcards_module $mod, array $fields): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('mod_wordcards')->create_term($mod->get_id(), $fields);
    }

    public function test_plan(): void {
        [$mod] = $this->setup_activity();
        $term = $this->create_term($mod, [
            'term' => 'kuusi',
            'definition' => 'spruce',
            'translations' => json_encode(['ja' => 'トウヒ']),
            'model_sentence' => 'Kuusi kasvaa metsässä.',
            'model_sentence_translations' => json_encode(['es' => 'El abeto crece en el bosque.']),
        ]);

        $plan = completer::plan($term, $mod, false);
        $this->assertFalse($plan['needsentence']);
        // The definition is the English word, so only languages without a word are asked about.
        $this->assertSame(['ja' => 'トウヒ', 'en' => 'spruce'], $plan['knownwords']);
        $this->assertNotContains('en', $plan['wordlangs']);
        $this->assertNotContains('ja', $plan['wordlangs']);
        $this->assertNotContains('fi', $plan['wordlangs']);
        $this->assertContains('es', $plan['wordlangs']);
        // The dictionary will translate its own sentence for the languages it has a word in.
        $this->assertNotContains('ja', $plan['sentencelangs']);
        // A sentence already there is not asked for again.
        $this->assertNotContains('es', $plan['sentencelangs']);
        $this->assertContains('de', $plan['sentencelangs']);

        // A sentence of the teacher's own is held by no dictionary entry, so it is ours to translate.
        $plan = completer::plan($term, $mod, true);
        $this->assertContains('ja', $plan['sentencelangs']);
        $this->assertContains('en', $plan['sentencelangs']);
        $this->assertNotContains('es', $plan['sentencelangs']);
        $this->assertTrue(completer::has_work($plan));
    }

    public function test_complete_term_merges(): void {
        global $DB;
        [$mod, $translator] = $this->setup_activity();
        $term = $this->create_term($mod, [
            'term' => 'kuusi',
            'definition' => 'spruce',
            'translations' => json_encode(['ja' => 'トウヒ']),
            'model_sentence' => 'Kuusi kasvaa metsässä.',
        ]);
        $translator->translatereply = [
            // The word already stored for Japanese must not be replaced.
            'ja' => ['w' => '樅', 's' => 'トウヒは森で育つ。'],
            'es' => ['w' => 'abeto', 's' => 'El abeto crece en el bosque.'],
            // The model answers to ru, and gives a word in the wrong script.
            'ru' => ['w' => 'spruce', 's' => 'Ель растёт в лесу.'],
            // Not asked for.
            'xx' => ['w' => 'nope', 's' => 'nope'],
        ];

        $result = completer::complete_term($term, false, $translator);

        $stored = $DB->get_record(constants::M_TERMSTABLE, ['id' => $term->id]);
        $words = json_decode($stored->translations, true);
        $sentences = json_decode($stored->model_sentence_translations, true);
        $this->assertSame('トウヒ', $words['ja']);
        $this->assertSame('abeto', $words['es']);
        $this->assertArrayNotHasKey('rus', $words);
        $this->assertArrayNotHasKey('xx', $words);
        $this->assertSame('El abeto crece en el bosque.', $sentences['es']);
        $this->assertSame('Ель растёт в лесу.', $sentences['rus']);
        // Japanese has the dictionary's word, so its sentence is left for the dictionary.
        $this->assertArrayNotHasKey('ja', $sentences);
        $this->assertSame(1, $result['words']);
        $this->assertSame(2, $result['sentences']);
        // Fifty languages go in requests of ten.
        $this->assertGreaterThan(1, count($translator->prompts));
    }

    public function test_complete_term_makes_sentence(): void {
        global $DB;
        [$mod, $translator] = $this->setup_activity();
        $term = $this->create_term($mod, [
            'term' => 'kuusi',
            'definition' => 'spruce',
            'translations' => json_encode(['ja' => 'トウヒ']),
            'model_sentence' => '',
        ]);
        $translator->sentencereply = ['sentence' => 'Kuusi kasvaa metsässä.'];
        $translator->translatereply = ['ja' => ['w' => 'トウヒ', 's' => 'トウヒは森で育つ。']];

        $result = completer::complete_term($term, false, $translator);

        $stored = $DB->get_record(constants::M_TERMSTABLE, ['id' => $term->id]);
        $this->assertTrue($result['sentencemade']);
        $this->assertSame('Kuusi kasvaa metsässä.', $stored->model_sentence);
        $this->assertStringContainsString('"spruce"', $translator->prompts[0]);
        // The sentence is new, so every language is translated, Japanese included, built around the
        // word already stored for it.
        $this->assertStringContainsString('ja (Japanese) = "トウヒ"', implode(' ', $translator->prompts));
        $this->assertSame('トウヒは森で育つ。', json_decode($stored->model_sentence_translations, true)['ja']);
    }

    public function test_complete_term_without_sentence(): void {
        global $DB;
        [$mod, $translator] = $this->setup_activity();
        $term = $this->create_term($mod, ['term' => 'kuusi', 'definition' => 'spruce', 'model_sentence' => '']);
        $translator->sentencereply = ['sentence' => ''];

        $result = completer::complete_term($term, false, $translator);

        // Nothing to say which sense is meant, so nothing is translated.
        $this->assertFalse($result['sentencemade']);
        $this->assertCount(1, $translator->prompts);
        $stored = $DB->get_record(constants::M_TERMSTABLE, ['id' => $term->id]);
        $this->assertEmpty($stored->translations);
    }

    public function test_create_senses(): void {
        [, $translator] = $this->setup_activity();
        $translator->sentencereply = null;
        $translator->translatereply = ['senses' => [
            ['d' => 'in Helsinki', 'en' => 'in Helsinki', 's' => 'Asun Helsingissä.'],
            // No sentence, so nothing says which meaning it is.
            ['d' => 'at Helsinki', 'en' => 'at Helsinki', 's' => ''],
            ['d' => 'Helsinki', 'en' => 'Helsinki', 's' => 'Helsingissä sataa.'],
            'not a sense',
        ]];

        $senses = $translator->create_senses('Helsingissä', 'fi', 'en');
        $this->assertSame([
            ['definition' => 'in Helsinki', 'sourcedefinition' => 'in Helsinki', 'modelsentence' => 'Asun Helsingissä.'],
            ['definition' => 'Helsinki', 'sourcedefinition' => 'Helsinki', 'modelsentence' => 'Helsingissä sataa.'],
        ], $senses);
        // Definitions not in Japanese script are no use to a learner reading Japanese.
        $this->assertSame([], $translator->create_senses('Helsingissä', 'fi', 'ja'));
        $this->assertCount(1, $translator->create_senses('Helsingissä', 'fi', 'en', 1));

        $translator->translatereply = ['senses' => []];
        $this->assertSame([], $translator->create_senses('xqzzt', 'fi', 'en'));
    }

    public function test_ai_entry(): void {
        $entry = \mod_wordcards\local\wordwizard\word_adder::ai_entry('nälkäinen kuin susi', [
            ['definition' => 'hungry as a horse', 'sourcedefinition' => 'hungry as a horse',
                'modelsentence' => 'Olen nälkäinen kuin susi.'],
        ], 'en');
        $this->assertTrue($entry['found']);
        $this->assertTrue($entry['senses'][0]['aimade']);
        $this->assertSame('hungry as a horse', $entry['senses'][0]['definition']);
        $this->assertSame('{"en":"hungry as a horse"}', $entry['senses'][0]['translations']);
        $this->assertSame('{}', $entry['senses'][0]['modelsentencetranslations']);

        $this->assertFalse(\mod_wordcards\local\wordwizard\word_adder::ai_entry('xqzzt', [], 'en')['found']);
    }

    public function test_read_json(): void {
        $this->assertSame(['a' => 1], translator::read_json("```json\n{\"a\": 1}\n```"));
        $this->assertSame(['a' => ['w' => 'x']], translator::read_json('Here you go: {"a": {"w": "x"}}'));
        $this->assertNull(translator::read_json('no json here'));
        $this->assertNull(translator::read_json('{broken'));
    }

    public function test_is_wrong_script(): void {
        $this->assertFalse(translator::is_wrong_script('ja', 'トウヒ'));
        $this->assertTrue(translator::is_wrong_script('ja', 'spruce'));
        $this->assertTrue(translator::is_wrong_script('ti', 'فانوس'));
        $this->assertFalse(translator::is_wrong_script('es', 'abeto'));
        $this->assertTrue(translator::is_wrong_script('bs', 'oluјan'));
        $this->assertFalse(translator::is_wrong_script('es', '6'));
        $this->assertTrue(translator::is_wrong_script('es', '-'));
    }
}
