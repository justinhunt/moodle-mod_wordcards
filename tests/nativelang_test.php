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

namespace mod_wordcards;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Tests for the native language preference shared with mod_minilesson.
 *
 * @package    mod_wordcards
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(utils::class)]
#[CoversClass(\mod_wordcards_external::class)]
final class nativelang_test extends \advanced_testcase {
    /**
     * Every language a learner can choose in the chooser can be stored in the preference and read back.
     */
    public function test_every_chooser_language_round_trips(): void {
        foreach (utils::get_rcdic_langs('en', false) as $langdef) {
            $locale = utils::deflang_to_locale($langdef['code']);
            $this->assertNotSame('', $locale, $langdef['code']);
            $this->assertSame($langdef['code'], utils::locale_to_deflang($locale));
        }
    }

    /**
     * Locales and their definitions languages.
     *
     * @return array
     */
    public static function locale_provider(): array {
        return [
            'exact' => ['ja-JP', 'ja'],
            'other region' => ['en-GB', 'en'],
            'russian' => ['ru-RU', 'rus'],
            'simplified chinese' => ['zh-CN', 'zh'],
            'traditional chinese' => ['zh-TW', 'zh_tw'],
            'norwegian' => ['no-NO', 'nb'],
            'tigrinya' => ['ti-ER', 'ti'],
            'already a definitions language' => ['zh_tw', 'zh_tw'],
            'no definitions language' => ['mi-NZ', ''],
            'minilesson none' => ['0', ''],
            'empty' => ['', ''],
        ];
    }

    /**
     * Locales from the preference map to the right definitions language.
     *
     * @param string $locale
     * @param string $expected
     * @dataProvider locale_provider
     */
    #[DataProvider('locale_provider')]
    public function test_locale_to_deflang(string $locale, string $expected): void {
        $this->assertSame($expected, utils::locale_to_deflang($locale));
    }

    /**
     * The chooser stores a locale under the shared name, and an empty choice clears it.
     *
     * The external class includes lib/externallib.php, which needs a process of its own.
     *
     * @runInSeparateProcess
     */
    #[RunInSeparateProcess]
    public function test_set_user_preference(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertTrue(\mod_wordcards_external::set_user_preference(constants::NATIVELANG_PREF, 'rus'));
        $this->assertSame('ru-RU', get_user_preferences(constants::NATIVELANG_PREF));
        $this->assertSame('rus', utils::get_user_deflang());

        // A page loaded before the upgrade still sends the old name.
        $this->assertTrue(\mod_wordcards_external::set_user_preference(constants::LEGACY_DEFLANG_PREF, 'ja'));
        $this->assertSame('ja-JP', get_user_preferences(constants::NATIVELANG_PREF));
        $this->assertNull(get_user_preferences(constants::LEGACY_DEFLANG_PREF));

        // A locale is kept as it is.
        $this->assertTrue(\mod_wordcards_external::set_user_preference(constants::NATIVELANG_PREF, 'en-GB'));
        $this->assertSame('en-GB', get_user_preferences(constants::NATIVELANG_PREF));

        // Anything else is refused and leaves the preference alone.
        $this->assertFalse(\mod_wordcards_external::set_user_preference(constants::NATIVELANG_PREF, 'klingon'));
        $this->assertSame('en-GB', get_user_preferences(constants::NATIVELANG_PREF));

        $this->assertTrue(\mod_wordcards_external::set_user_preference(constants::NATIVELANG_PREF, ''));
        $this->assertNull(get_user_preferences(constants::NATIVELANG_PREF));
        $this->assertSame('', utils::get_user_deflang());
    }

    /**
     * The upgrade moves the old preference across, keeping a choice already made in MiniLesson.
     */
    public function test_upgrade_moves_preference(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/wordcards/db/upgrade.php');
        $this->resetAfterTest();

        $moved = $this->getDataGenerator()->create_user();
        $kept = $this->getDataGenerator()->create_user();
        $unknown = $this->getDataGenerator()->create_user();
        set_user_preference(constants::LEGACY_DEFLANG_PREF, 'zh_tw', $moved);
        set_user_preference(constants::LEGACY_DEFLANG_PREF, 'ja', $kept);
        set_user_preference(constants::NATIVELANG_PREF, 'ko-KR', $kept);
        set_user_preference(constants::LEGACY_DEFLANG_PREF, 'zz', $unknown);

        set_config('version', 2026102301, 'mod_wordcards');
        xmldb_wordcards_upgrade(2026102301);

        $this->assertSame('zh-TW', get_user_preferences(constants::NATIVELANG_PREF, null, $moved->id));
        $this->assertSame('ko-KR', get_user_preferences(constants::NATIVELANG_PREF, null, $kept->id));
        $this->assertNull(get_user_preferences(constants::NATIVELANG_PREF, null, $unknown->id));
        $this->assertFalse($DB->record_exists('user_preferences', ['name' => constants::LEGACY_DEFLANG_PREF]));
    }
}
