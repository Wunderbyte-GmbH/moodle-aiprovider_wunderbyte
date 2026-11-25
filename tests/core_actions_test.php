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

use aiprovider_wunderbyte\aiactions\planner_decide;
use aiprovider_wunderbyte\form\action_chat_form;
use aiprovider_wunderbyte\form\action_image_form;
use aiprovider_wunderbyte\local\core_actions_seeder;
use core_ai\aiactions\explain_text;
use core_ai\aiactions\generate_image;
use core_ai\aiactions\generate_text;
use core_ai\aiactions\summarise_text;

/**
 * The provider offers Moodle's actions summarise_text, explain_text and generate_image.
 *
 * A site that points the provider to a model of its own can use Moodle's placements with it. What the tests pin:
 * the actions are offered with a settings form; a new instance has the two text actions switched on with the
 * instruction Moodle defines for them and image generation switched off without a model; an existing instance gets
 * the three entries switched off on upgrade and keeps everything it had.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\provider
 * @covers     \aiprovider_wunderbyte\form\action_chat_form
 * @covers     \aiprovider_wunderbyte\form\action_image_form
 * @covers     \aiprovider_wunderbyte\local\core_actions_seeder
 * @covers     \aiprovider_wunderbyte\process_summarise_text
 * @covers     \aiprovider_wunderbyte\process_explain_text
 */
final class core_actions_test extends \advanced_testcase {
    /** @var string[] The two text actions with their processors. */
    private const TEXT_ACTIONS = [
        summarise_text::class => process_summarise_text::class,
        explain_text::class => process_explain_text::class,
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The decoded request body a processor would send.
     *
     * @param object $processor
     * @return \stdClass
     */
    private function request_body(object $processor): \stdClass {
        $method = new \ReflectionMethod($processor, 'create_request_object');
        return json_decode($method->invoke($processor, 'hashed user')->getBody()->getContents());
    }

    /**
     * The stored action configuration of an instance.
     *
     * @param int $id
     * @return array
     */
    private function stored_config(int $id): array {
        global $DB;
        return json_decode((string)$DB->get_field('ai_providers', 'actionconfig', ['id' => $id]), true);
    }

    /**
     * The three actions are in the list, each with a settings form.
     */
    public function test_the_actions_are_offered_with_a_settings_form(): void {
        $offered = provider::get_action_list();
        foreach ([summarise_text::class, explain_text::class, generate_image::class] as $actionclass) {
            $this->assertContains($actionclass, $offered);
        }
        $this->assertInstanceOf(action_chat_form::class, provider::get_action_settings(summarise_text::class));
        $this->assertInstanceOf(action_chat_form::class, provider::get_action_settings(explain_text::class));
        $this->assertInstanceOf(action_image_form::class, provider::get_action_settings(generate_image::class));
    }

    /**
     * A new instance: the text actions are on, on the default chat model, with the instruction Moodle defines for
     * them; image generation is off and has no model.
     */
    public function test_the_defaults_of_a_new_instance(): void {
        $config = provider::initialise_action_settings();

        foreach (array_keys(self::TEXT_ACTIONS) as $actionclass) {
            $this->assertTrue($config[$actionclass]['enabled'], $actionclass);
            $settings = $config[$actionclass]['settings'];
            $this->assertSame('https://llm.wunderbyte.at/v1/chat/completions', $settings['endpoint'], $actionclass);
            $this->assertSame('wunderbyte-privat', $settings['model'], $actionclass);
            $this->assertNotSame('', $actionclass::get_system_instruction(), $actionclass);
            $this->assertSame($actionclass::get_system_instruction(), $settings['systeminstruction'], $actionclass);
        }

        $this->assertFalse($config[generate_image::class]['enabled']);
        $this->assertSame(action_image_form::DEFAULT_ENDPOINT, $config[generate_image::class]['settings']['endpoint']);
        $this->assertSame('', (string)($config[generate_image::class]['settings']['model'] ?? ''));

        // The actions of the agent and generate_text keep their rule: on, and no instruction of the provider's own.
        $this->assertTrue($config[planner_decide::class]['enabled']);
        $this->assertSame('', (string)$config[planner_decide::class]['settings']['systeminstruction']);
        $this->assertSame('', (string)$config[generate_text::class]['settings']['systeminstruction']);
    }

    /**
     * The request of a text action carries the instruction of the instance and the text of the placement.
     */
    public function test_a_text_action_sends_the_instruction_and_the_text(): void {
        $manager = \core\di::get(\core_ai\manager::class);
        $withdefaults = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'defaults',
            config: ['apikey' => '123'],
        );
        $actionconfig = provider::initialise_action_settings();
        foreach (array_keys(self::TEXT_ACTIONS) as $actionclass) {
            $actionconfig[$actionclass]['settings']['systeminstruction'] = '';
            $actionconfig[$actionclass]['settings']['model'] = 'the model of the site';
        }
        $withoutinstruction = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'instruction emptied by the administrator',
            config: ['apikey' => '123'],
            actionconfig: $actionconfig,
        );

        foreach (self::TEXT_ACTIONS as $actionclass => $processorclass) {
            $action = new $actionclass(contextid: 1, userid: 2, prompttext: 'The text of the page.');

            $body = $this->request_body(new $processorclass($withdefaults, $action));
            $this->assertSame('wunderbyte-privat', $body->model, $actionclass);
            $this->assertCount(2, $body->messages, $actionclass);
            $this->assertSame('system', $body->messages[0]->role, $actionclass);
            $this->assertSame($actionclass::get_system_instruction(), $body->messages[0]->content, $actionclass);
            $this->assertSame('user', $body->messages[1]->role, $actionclass);
            $this->assertSame('The text of the page.', $body->messages[1]->content, $actionclass);
            $this->assertSame('hashed user', $body->user, $actionclass);

            // An administrator's setting wins, an emptied instruction included.
            $body = $this->request_body(new $processorclass($withoutinstruction, $action));
            $this->assertSame('the model of the site', $body->model, $actionclass);
            $this->assertCount(1, $body->messages, $actionclass);
            $this->assertSame('The text of the page.', $body->messages[0]->content, $actionclass);
        }
    }

    /**
     * The upgrade gives an existing instance the three entries, switched off, and changes nothing else. The text
     * actions start with the endpoint and the model of the instance's generate_text.
     */
    public function test_the_upgrade_adds_the_actions_switched_off(): void {
        $manager = \core\di::get(\core_ai\manager::class);
        $own = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'a site with a model of its own',
            enabled: true,
            config: ['apikey' => '123'],
            actionconfig: [
                planner_decide::class => [
                    'enabled' => true,
                    'settings' => ['model' => 'planner', 'endpoint' => 'https://ai.example.org/v1/chat/completions'],
                ],
                generate_text::class => [
                    'enabled' => true,
                    'settings' => [
                        'model' => 'own-model',
                        'endpoint' => 'https://ai.example.org/v1/chat/completions',
                        'systeminstruction' => '',
                        'modelextraparams' => '{"max_tokens": 900}',
                    ],
                ],
            ],
        );
        $configured = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'an instance that has an entry already',
            config: ['apikey' => '456'],
            actionconfig: [
                generate_text::class => ['enabled' => true, 'settings' => ['model' => 'm', 'endpoint' => 'https://e']],
                summarise_text::class => [
                    'enabled' => true,
                    'settings' => ['model' => 'chosen by the administrator', 'endpoint' => 'https://e'],
                ],
            ],
        );
        $before = $this->stored_config((int)$own->id);

        $changed = core_actions_seeder::seed_missing();
        $again = core_actions_seeder::seed_missing();

        $this->assertSame(2, $changed);
        $this->assertSame(0, $again, 'a second run changes nothing');

        $after = $this->stored_config((int)$own->id);
        // What the instance had is untouched.
        $this->assertSame($before[planner_decide::class], $after[planner_decide::class]);
        $this->assertSame($before[generate_text::class], $after[generate_text::class]);

        foreach (array_keys(self::TEXT_ACTIONS) as $actionclass) {
            $entry = $after[$actionclass];
            $this->assertFalse($entry['enabled'], $actionclass);
            $this->assertSame('https://ai.example.org/v1/chat/completions', $entry['settings']['endpoint'], $actionclass);
            $this->assertSame('own-model', $entry['settings']['model'], $actionclass);
            $this->assertSame('{"max_tokens": 900}', $entry['settings']['modelextraparams'], $actionclass);
            $this->assertSame($actionclass::get_system_instruction(), $entry['settings']['systeminstruction'], $actionclass);
            // Moodle does not hand the action to the instance until an administrator switches it on.
            $this->assertFalse($manager->is_action_enabled('aiprovider_wunderbyte', $actionclass, (int)$own->id));
            $this->assertFalse($manager->is_action_available($actionclass), $actionclass);
        }
        $this->assertFalse($after[generate_image::class]['enabled']);
        $this->assertSame(action_image_form::DEFAULT_ENDPOINT, $after[generate_image::class]['settings']['endpoint']);
        $this->assertSame('', $after[generate_image::class]['settings']['model']);
        $this->assertFalse($manager->is_action_available(generate_image::class));

        // An entry an administrator made is never changed; the missing ones are added beside it.
        $kept = $this->stored_config((int)$configured->id);
        $this->assertTrue($kept[summarise_text::class]['enabled']);
        $this->assertSame('chosen by the administrator', $kept[summarise_text::class]['settings']['model']);
        $this->assertFalse($kept[explain_text::class]['enabled']);
        $this->assertFalse($kept[generate_image::class]['enabled']);
    }

    /**
     * After an administrator switched a seeded action on, Moodle hands it to the instance.
     */
    public function test_a_seeded_action_works_once_it_is_switched_on(): void {
        $manager = \core\di::get(\core_ai\manager::class);
        $instance = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'existing',
            enabled: true,
            config: ['apikey' => '123'],
            actionconfig: [
                generate_text::class => [
                    'enabled' => true,
                    'settings' => ['model' => 'own-model', 'endpoint' => 'https://ai.example.org/v1/chat/completions'],
                ],
            ],
        );
        core_actions_seeder::seed_missing();

        $manager->set_action_state('aiprovider_wunderbyte', 'summarise_text', 1, (int)$instance->id);

        $this->assertTrue($manager->is_action_available(summarise_text::class));
        $providers = $manager->get_provider_instances(['id' => (int)$instance->id]);
        $provider = reset($providers);
        $body = $this->request_body(new process_summarise_text(
            $provider,
            new summarise_text(contextid: 1, userid: 2, prompttext: 'The text of the page.'),
        ));
        $this->assertSame('own-model', $body->model);
        $this->assertSame(summarise_text::get_system_instruction(), $body->messages[0]->content);
    }
}
