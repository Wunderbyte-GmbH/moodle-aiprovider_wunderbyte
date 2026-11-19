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

use aiprovider_wunderbyte\aiactions\generate_content;
use aiprovider_wunderbyte\aiactions\inspect_image;
use aiprovider_wunderbyte\aiactions\responses\response_inspect_image;
use aiprovider_wunderbyte\form\action_chat_form;
use aiprovider_wunderbyte\local\inspect_image_seeder;
use core_ai\aiactions\generate_text;

/**
 * The inspect_image action: offered with the chat form, the images travel as content parts, the table keeps no image.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_wunderbyte\aiactions\inspect_image
 * @covers \aiprovider_wunderbyte\local\inspect_image_seeder
 * @covers \aiprovider_wunderbyte\process_inspect_image
 * @covers \aiprovider_wunderbyte\process_chat_action
 */
final class inspect_image_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a provider instance with one configured action.
     *
     * @param string $actionclass
     * @return \core_ai\provider
     */
    private function provider_with(string $actionclass): \core_ai\provider {
        return \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'images',
            config: ['apikey' => '123'],
            actionconfig: [
                $actionclass => [
                    'enabled' => true,
                    'settings' => [
                        'model' => 'wunderbyte-privat',
                        'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions',
                    ],
                ],
            ],
        );
    }

    /**
     * The decoded request body a processor would send.
     *
     * @param object $processor
     * @return \stdClass
     */
    private function request_body(object $processor): \stdClass {
        $method = new \ReflectionMethod($processor, 'create_request_object');
        return json_decode($method->invoke($processor, 1)->getBody()->getContents());
    }

    /**
     * The action is in the provider's list and configured with the chat form on the default chat model.
     */
    public function test_the_action_is_offered_with_the_chat_form(): void {
        $this->assertContains(inspect_image::class, provider::get_action_list());
        $this->assertInstanceOf(action_chat_form::class, provider::get_action_settings(inspect_image::class));
        $this->assertSame('wunderbyte-privat', provider::get_action_setting_defaults(inspect_image::class)['model']);
    }

    /**
     * The user message carries the prompt as a text part and every image as an image part.
     */
    public function test_the_images_travel_as_content_parts(): void {
        $images = [
            ['mimetype' => 'image/jpeg', 'data' => 'QUJD'],
            ['mimetype' => 'image/png', 'data' => 'REVG'],
            ['mimetype' => '', 'data' => 'no type, left out'],
        ];
        $processor = new process_inspect_image(
            $this->provider_with(inspect_image::class),
            new inspect_image(contextid: 1, userid: 2, prompttext: 'what does it show', images: $images)
        );
        $body = $this->request_body($processor);

        $this->assertSame('wunderbyte-privat', $body->model);
        $content = end($body->messages)->content;
        $this->assertIsArray($content);
        $this->assertCount(3, $content);
        $this->assertSame('text', $content[0]->type);
        $this->assertSame('what does it show', $content[0]->text);
        $this->assertSame('image_url', $content[1]->type);
        $this->assertSame('data:image/jpeg;base64,QUJD', $content[1]->image_url->url);
        $this->assertSame('data:image/png;base64,REVG', $content[2]->image_url->url);
    }

    /**
     * Every other chat action still sends its prompt as a plain string.
     */
    public function test_the_other_chat_actions_keep_a_plain_string(): void {
        $processor = new process_generate_content(
            $this->provider_with(generate_content::class),
            new generate_content(1, 2, 'chapter prompt')
        );
        $this->assertSame('chapter prompt', end($this->request_body($processor)->messages)->content);
    }

    /**
     * The table keeps the prompt and the answer, never the image.
     */
    public function test_store_keeps_the_prompt_and_no_image(): void {
        global $DB;

        $action = new inspect_image(1, 2, 'what does it show', [['mimetype' => 'image/png', 'data' => 'SECRETIMAGEDATA']]);
        $response = new response_inspect_image(true);
        $response->set_response_data(['id' => 'r1', 'generatedcontent' => 'a bee', 'finishreason' => 'stop']);
        $id = $action->store($response);

        $record = $DB->get_record('ai_action_inspect_image', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('what does it show', $record->prompt);
        $this->assertSame('a bee', $record->generatedcontent);
        $this->assertStringNotContainsString('SECRETIMAGEDATA', json_encode($record));
        $this->assertSame('inspect_image', $response->get_actionname());
    }

    /**
     * The upgrade seeds the entry from generate_text once per instance and leaves a configured one alone.
     */
    public function test_the_seeder_derives_the_entry_from_generate_text_once(): void {
        global $DB;

        $legacy = \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'legacy',
            config: ['apikey' => '123'],
            actionconfig: [
                generate_text::class => [
                    'enabled' => true,
                    'settings' => ['model' => 'wunderbyte-privat', 'endpoint' => 'https://e/v1/chat/completions'],
                ],
            ],
        );

        $this->assertSame(1, inspect_image_seeder::seed_missing());
        $this->assertSame(0, inspect_image_seeder::seed_missing(), 'the upgrade step is idempotent');

        $actionconfig = json_decode($DB->get_field('ai_providers', 'actionconfig', ['id' => $legacy->id]), true);
        $entry = $actionconfig[inspect_image::class];
        $this->assertTrue($entry['enabled']);
        $this->assertSame('wunderbyte-privat', $entry['settings']['model']);
        $this->assertSame('https://e/v1/chat/completions', $entry['settings']['endpoint']);
    }
}
