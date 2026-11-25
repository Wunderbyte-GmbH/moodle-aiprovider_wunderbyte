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

use aiprovider_wunderbyte\aiactions\generate_content;
use aiprovider_wunderbyte\aiactions\responses\response_generate_content;
use aiprovider_wunderbyte\form\action_chat_form;
use aiprovider_wunderbyte\local\generate_content_seeder;
use core_ai\aiactions\generate_text;

/**
 * The generate_content action: offered with the chat form, its own table and model alias, seeded into existing instances.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_wunderbyte\aiactions\generate_content
 * @covers \aiprovider_wunderbyte\local\generate_content_seeder
 * @covers \aiprovider_wunderbyte\process_generate_content
 */
final class generate_content_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The action is in the provider's list, configured with the chat form, on the content alias by default.
     */
    public function test_the_action_is_offered_with_the_chat_form_on_the_content_alias(): void {
        $this->assertContains(generate_content::class, provider::get_action_list());
        $this->assertInstanceOf(action_chat_form::class, provider::get_action_settings(generate_content::class));

        $defaults = provider::get_action_setting_defaults(generate_content::class);
        $this->assertSame('wunderbyte-privat-content', $defaults['model']);
        $this->assertSame('wunderbyte-privat-content', action_chat_form::default_model('generate_content'));
        $this->assertSame('wunderbyte-privat', action_chat_form::default_model('generate_text'));
    }

    /**
     * Results land in the action's own table.
     */
    public function test_store_writes_to_its_own_table(): void {
        global $DB;

        $action = new generate_content(contextid: 1, userid: 2, prompttext: 'chapter prompt');
        $response = new response_generate_content(true);
        $response->set_response_data(['id' => 'r1', 'generatedcontent' => '<p>text</p>', 'finishreason' => 'stop']);

        $before = $DB->count_records('aiprovider_wunderbyte_generate_content');
        $id = $action->store($response);

        $this->assertSame($before + 1, $DB->count_records('aiprovider_wunderbyte_generate_content'));
        $this->assertSame('chapter prompt', $DB->get_field('aiprovider_wunderbyte_generate_content', 'prompt', ['id' => $id]));
        $this->assertSame('generate_content', $response->get_actionname());
    }

    /**
     * The request names the content model of the instance.
     */
    public function test_the_request_goes_to_the_content_model(): void {
        $manager = \core\di::get(\core_ai\manager::class);
        $provider = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'content',
            config: ['apikey' => '123'],
            actionconfig: [
                generate_content::class => [
                    'enabled' => true,
                    'settings' => [
                        'model' => 'wunderbyte-privat-content',
                        'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions',
                    ],
                ],
            ],
        );
        $processor = new process_generate_content($provider, new generate_content(1, 2, 'p'));
        $method = new \ReflectionMethod($processor, 'create_request_object');
        $body = json_decode($method->invoke($processor, 1)->getBody()->getContents());

        $this->assertSame('wunderbyte-privat-content', $body->model);
    }

    /**
     * The upgrade seeds the entry from generate_text (endpoint, extra parameters) once per instance.
     */
    public function test_the_seeder_derives_the_entry_from_generate_text_once(): void {
        global $DB;

        $manager = \core\di::get(\core_ai\manager::class);
        $legacy = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'legacy',
            config: ['apikey' => '123'],
            actionconfig: [
                generate_text::class => [
                    'enabled' => true,
                    'settings' => [
                        'model' => 'wunderbyte-privat',
                        'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions',
                        'modelextraparams' => '{"max_tokens": 8192}',
                    ],
                ],
            ],
        );
        $configured = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'configured',
            config: ['apikey' => '456'],
            actionconfig: [
                generate_content::class => [
                    'enabled' => true,
                    'settings' => ['model' => 'admin-choice', 'endpoint' => 'https://e'],
                ],
            ],
        );
        $configuredbefore = $DB->get_field('ai_providers', 'actionconfig', ['id' => $configured->id]);

        $this->assertSame(1, generate_content_seeder::seed_missing());
        $this->assertSame(0, generate_content_seeder::seed_missing(), 'the upgrade step is idempotent');

        $actionconfig = json_decode($DB->get_field('ai_providers', 'actionconfig', ['id' => $legacy->id]), true);
        $entry = $actionconfig[generate_content::class];
        $this->assertTrue($entry['enabled']);
        $this->assertSame('wunderbyte-privat-content', $entry['settings']['model']);
        $this->assertSame('https://llm.wunderbyte.at/v1/chat/completions', $entry['settings']['endpoint']);
        $this->assertSame('{"max_tokens": 8192}', $entry['settings']['modelextraparams']);
        $this->assertSame('', $entry['settings']['systeminstruction']);
        $this->assertSame($configuredbefore, $DB->get_field('ai_providers', 'actionconfig', ['id' => $configured->id]));
    }
}
