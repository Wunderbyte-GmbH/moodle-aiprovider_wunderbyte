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

namespace aiprovider_wunderbyte;

/**
 * The plugin ships English and German, and the two language packs hold the same strings.
 *
 * English and German are the languages every Wunderbyte plugin supports at least. A string that exists in one pack
 * only shows up as a placeholder in the other language; a placeholder that is missing in a translation drops a value
 * from the sentence.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lang_packs_test extends \basic_testcase {
    /**
     * The strings of one language pack of the plugin.
     *
     * @param string $lang Language code.
     * @return string[]
     */
    private function strings(string $lang): array {
        $string = [];
        $file = \core_component::get_plugin_directory('aiprovider', 'wunderbyte') . "/lang/$lang/aiprovider_wunderbyte.php";
        $this->assertFileExists($file);
        include($file);
        return $string;
    }

    /**
     * German has every string English has, and no other, in the same order.
     */
    public function test_german_has_the_strings_of_english(): void {
        $english = $this->strings('en');
        $german = $this->strings('de');

        $this->assertNotEmpty($english);
        $this->assertSame(array_keys($english), array_keys($german));
        foreach ($german as $key => $text) {
            $this->assertNotSame('', trim($text), "$key is empty in German");
        }
    }

    /**
     * A translation uses the placeholders of the English string.
     */
    public function test_the_placeholders_are_the_same(): void {
        $german = $this->strings('de');
        foreach ($this->strings('en') as $key => $text) {
            $this->assertSame(
                $this->placeholders($text),
                $this->placeholders($german[$key] ?? ''),
                "placeholders of $key",
            );
        }
    }

    /**
     * The placeholders of a string, sorted.
     *
     * @param string $text
     * @return string[]
     */
    private function placeholders(string $text): array {
        preg_match_all('/\{\$a(?:->\w+)?\}/', $text, $matches);
        $found = $matches[0];
        sort($found);
        return $found;
    }
}
