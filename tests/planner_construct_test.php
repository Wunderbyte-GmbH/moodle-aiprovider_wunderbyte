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

use aiprovider_wunderbyte\aiactions\planner_construct;
use aiprovider_wunderbyte\aiactions\planner_decide;
use aiprovider_wunderbyte\aiactions\responses\response_planner_construct;
use aiprovider_wunderbyte\form\action_chat_form;

/**
 * The agent's constructor phase has its own action with its own model (phase model tiers).
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\aiactions\planner_construct
 * @covers     \aiprovider_wunderbyte\process_planner_construct
 * @covers     \aiprovider_wunderbyte\form\action_chat_form
 */
final class planner_construct_test extends \advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * An instance with both planner actions on different models.
     *
     * @return \core_ai\provider
     */
    private function create_tiered_provider(): \core_ai\provider {
        $manager = \core\di::get(\core_ai\manager::class);
        return $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'tiers',
            config: ['apikey' => '123'],
            actionconfig: [
                planner_decide::class => [
                    'enabled' => true,
                    'settings' => [
                        'model' => 'small-model',
                        'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions',
                        'modelextraparams' => '{"reasoning_effort": "low"}',
                    ],
                ],
                planner_construct::class => [
                    'enabled' => true,
                    'settings' => [
                        'model' => 'large-model',
                        'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions',
                        'modelextraparams' => '{"max_tokens": 4096}',
                    ],
                ],
            ],
        );
    }

    /**
     * The action is offered with the chat settings form.
     */
    public function test_action_uses_the_chat_settings_form(): void {
        $form = provider::get_action_settings(planner_construct::class);
        $this->assertInstanceOf(action_chat_form::class, $form);
        $defaults = provider::get_action_setting_defaults(planner_construct::class);
        $this->assertSame('wunderbyte-privat', $defaults['model']);
    }

    /**
     * The selector defaults to the small alias, every other chat action to the large one.
     */
    public function test_defaults_differ_per_action(): void {
        $this->assertSame('wunderbyte-privat-mini', action_chat_form::default_model('planner_decide'));
        $this->assertSame('wunderbyte-privat', action_chat_form::default_model('planner_construct'));
        $this->assertSame('wunderbyte-privat', action_chat_form::default_model('generate_agent_reply'));
        $this->assertSame('wunderbyte-privat', action_chat_form::default_model('generate_text'));
    }

    /**
     * A stored construction goes into the planner_decide table; the action needs no table of its own.
     */
    public function test_store_writes_to_planner_decide_table(): void {
        global $DB;
        $action = new planner_construct(contextid: 1, userid: 2, prompttext: 'construct prompt');
        $response = new response_planner_construct(true);
        $response->set_response_data(['id' => 'r1', 'generatedcontent' => '{}', 'finishreason' => 'stop']);

        $before = $DB->count_records('aiprovider_wunderbyte_planner_decide');
        $id = $action->store($response);

        $this->assertSame($before + 1, $DB->count_records('aiprovider_wunderbyte_planner_decide'));
        $this->assertSame('construct prompt', $DB->get_field('aiprovider_wunderbyte_planner_decide', 'prompt', ['id' => $id]));
        $this->assertFalse($DB->get_manager()->table_exists('aiprovider_wunderbyte_planner_construct'));
        $this->assertSame('planner_construct', $response->get_actionname());
    }

    /**
     * The request carries the model and extra parameters of planner_construct, not those of planner_decide.
     */
    public function test_request_uses_own_model_and_extraparams(): void {
        $provider = $this->create_tiered_provider();
        $build = static function (string $processorclass, \core_ai\aiactions\base $action) use ($provider): \stdClass {
            $processor = new $processorclass($provider, $action);
            $method = new \ReflectionMethod($processor, 'create_request_object');
            return json_decode($method->invoke($processor, 1)->getBody()->getContents());
        };

        $construct = $build(process_planner_construct::class, new planner_construct(1, 2, 'p'));
        $decide = $build(process_planner_decide::class, new planner_decide(1, 2, 'p'));

        $this->assertSame('large-model', $construct->model);
        $this->assertSame(4096, $construct->max_tokens);
        $this->assertObjectNotHasProperty('reasoning_effort', $construct);
        $this->assertSame('small-model', $decide->model);
        $this->assertSame('low', $decide->reasoning_effort);
        // Only the model may differ: neither action sends a system message of its own.
        $this->assertSame('user', $construct->messages[0]->role);
        $this->assertSame('user', $decide->messages[0]->role);
    }

    /**
     * Every chat request names its own action in metadata.action (#2537).
     *
     * The gateway decides per deployment and per role whether a request parameter such as JSON mode
     * is applied; the provider only states which role it is sending. No model parameter leaves the
     * provider for this, and modelextraparams stay untouched.
     */
    public function test_request_metadata_names_the_action(): void {
        $provider = $this->create_tiered_provider();
        $build = static function (string $processorclass, \core_ai\aiactions\base $action) use ($provider): \stdClass {
            $processor = new $processorclass($provider, $action);
            $create = new \ReflectionMethod($processor, 'create_request_object');
            $attach = new \ReflectionMethod($processor, 'attach_site_metadata');
            $request = $attach->invoke($processor, $create->invoke($processor, 1));
            return json_decode($request->getBody()->getContents());
        };

        $construct = $build(process_planner_construct::class, new planner_construct(1, 2, 'p'));
        $decide = $build(process_planner_decide::class, new planner_decide(1, 2, 'p'));

        $this->assertSame('planner_construct', $construct->metadata->action);
        $this->assertSame('planner_decide', $decide->metadata->action);
        // The role is metadata, never a model parameter: the body carries no response_format.
        $this->assertObjectNotHasProperty('response_format', $construct);
        $this->assertObjectNotHasProperty('response_format', $decide);
    }
}
