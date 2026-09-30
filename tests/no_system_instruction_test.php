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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace aiprovider_wunderbyte;

use aiprovider_wunderbyte\aiactions\generate_agent_reply;
use aiprovider_wunderbyte\aiactions\planner_construct;
use aiprovider_wunderbyte\aiactions\planner_decide;
use aiprovider_wunderbyte\local\system_instruction_cleaner;
use core_ai\aiactions\generate_text;

/**
 * The provider sends no instruction of its own: every caller brings its complete prompt (George 2026-09-30).
 *
 * A per-action system message made switching an agent phase to another action change the model's input, not only the
 * model (baseline runs L56/L56b, Wunderbyte-GmbH/Wunderbyte-GmbH#2505). The admin field stays, empty by default.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\abstract_processor
 * @covers     \aiprovider_wunderbyte\form\action_chat_form
 * @covers     \aiprovider_wunderbyte\local\system_instruction_cleaner
 */
final class no_system_instruction_test extends \advanced_testcase {
    /** @var array<string, string> Chat action class => processor class. */
    private const CHAT_ACTIONS = [
        planner_decide::class => process_planner_decide::class,
        planner_construct::class => process_planner_construct::class,
        generate_agent_reply::class => process_generate_agent_reply::class,
        generate_text::class => process_generate_text::class,
    ];

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * An instance with all chat actions, optionally with a system instruction on each.
     *
     * @param string|null $instruction Null leaves the setting out.
     * @return \core_ai\provider
     */
    private function create_instance(?string $instruction): \core_ai\provider {
        $actionconfig = [];
        foreach (array_keys(self::CHAT_ACTIONS) as $actionclass) {
            $settings = ['model' => 'm', 'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions'];
            if ($instruction !== null) {
                $settings['systeminstruction'] = $instruction;
            }
            $actionconfig[$actionclass] = ['enabled' => true, 'settings' => $settings];
        }
        return \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'instructions',
            config: ['apikey' => '123'],
            actionconfig: $actionconfig,
        );
    }

    /**
     * Build the request body a chat action sends.
     *
     * @param \core_ai\provider $provider
     * @param string $actionclass
     * @return \stdClass
     */
    private function request_body(\core_ai\provider $provider, string $actionclass): \stdClass {
        $processorclass = self::CHAT_ACTIONS[$actionclass];
        $processor = new $processorclass($provider, new $actionclass(1, 2, 'the caller prompt'));
        $method = new \ReflectionMethod($processor, 'create_request_object');
        return json_decode($method->invoke($processor, 1)->getBody()->getContents());
    }

    /**
     * Without an instruction on the instance the request carries only the caller's prompt.
     */
    public function test_request_has_no_system_message_by_default(): void {
        foreach ([null, ''] as $instruction) {
            $provider = $this->create_instance($instruction);
            foreach (array_keys(self::CHAT_ACTIONS) as $actionclass) {
                $body = $this->request_body($provider, $actionclass);
                $this->assertCount(1, $body->messages, $actionclass);
                $this->assertSame('user', $body->messages[0]->role, $actionclass);
                $this->assertSame('the caller prompt', $body->messages[0]->content, $actionclass);
            }
        }
    }

    /**
     * An instruction an admin sets on the instance is sent as it stands.
     */
    public function test_instance_instruction_is_sent(): void {
        $provider = $this->create_instance('Admin instruction.');
        foreach (array_keys(self::CHAT_ACTIONS) as $actionclass) {
            $body = $this->request_body($provider, $actionclass);
            $this->assertSame('system', $body->messages[0]->role, $actionclass);
            $this->assertSame('Admin instruction.', $body->messages[0]->content, $actionclass);
        }
    }

    /**
     * New instances start with an empty instruction field for every chat action.
     */
    public function test_form_defaults_are_empty(): void {
        foreach (array_keys(self::CHAT_ACTIONS) as $actionclass) {
            $defaults = provider::get_action_setting_defaults($actionclass);
            $this->assertSame('', (string)($defaults['systeminstruction'] ?? ''), $actionclass);
        }
    }

    /**
     * The upgrade empties the instruction of every chat action on every instance and keeps all other settings.
     */
    public function test_upgrade_empties_every_chat_instruction(): void {
        global $DB;
        $first = $this->create_instance('Act as a compact planner and return a structured routing decision as plain JSON.');
        $second = $this->create_instance('A custom admin text.');

        system_instruction_cleaner::clear_all();

        foreach ([$first, $second] as $instance) {
            $config = json_decode((string)$DB->get_field('ai_providers', 'actionconfig', ['id' => $instance->id]), true);
            foreach (array_keys(self::CHAT_ACTIONS) as $actionclass) {
                $this->assertSame('', $config[$actionclass]['settings']['systeminstruction'], $actionclass);
                $this->assertSame('m', $config[$actionclass]['settings']['model'], $actionclass);
                $this->assertTrue($config[$actionclass]['enabled'], $actionclass);
            }
        }
    }
}
