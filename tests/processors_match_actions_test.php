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
 * The processors of the plugin are the processors of the actions it offers.
 *
 * Moodle's AI subsystem hands an action to a provider only when the provider lists it in get_action_list(), and
 * then creates the class process_<action> of the provider. A processor of an action that is not listed can never
 * run; an action without its processor fails on its first call.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\provider::get_action_list
 */
final class processors_match_actions_test extends \basic_testcase {
    /**
     * The processor class names the subsystem builds for the offered actions.
     *
     * @return string[]
     */
    private function processors_of_offered_actions(): array {
        return array_map(
            static fn(string $actionclass): string => 'process_' . $actionclass::get_basename(),
            provider::get_action_list(),
        );
    }

    /**
     * Every offered action has a processor the subsystem can create.
     */
    public function test_every_offered_action_has_its_processor(): void {
        foreach ($this->processors_of_offered_actions() as $name) {
            $class = '\\aiprovider_wunderbyte\\' . $name;
            $this->assertTrue(class_exists($class), "$name is missing");
            $this->assertTrue(is_subclass_of($class, \core_ai\process_base::class), "$name is no processor");
            $this->assertFalse((new \ReflectionClass($class))->isAbstract(), "$name cannot be created");
        }
    }

    /**
     * Every processor in the plugin belongs to an offered action; shared abstract bases aside.
     */
    public function test_every_processor_belongs_to_an_offered_action(): void {
        $offered = $this->processors_of_offered_actions();
        $directory = \core_component::get_plugin_directory('aiprovider', 'wunderbyte') . '/classes';

        $withoutaction = [];
        foreach (glob($directory . '/process_*.php') as $file) {
            $name = basename($file, '.php');
            if ((new \ReflectionClass('\\aiprovider_wunderbyte\\' . $name))->isAbstract()) {
                continue;
            }
            if (!in_array($name, $offered, true)) {
                $withoutaction[] = $name;
            }
        }

        $this->assertSame([], $withoutaction, 'processors of actions the provider does not offer');
    }
}
