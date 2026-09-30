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

use aiprovider_wunderbyte\aiactions\planner_decide;
use aiprovider_wunderbyte\external\get_usage;

/**
 * The usage bar reads the key of the instance it belongs to.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\external\get_usage
 * @covers     \aiprovider_wunderbyte\hook_listener
 */
final class get_usage_test extends \advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create an enabled Wunderbyte instance.
     *
     * @param string $name
     * @param string $apikey Empty for an instance without a key.
     * @param bool $plannerenabled
     * @return provider
     */
    private function create_instance(string $name, string $apikey, bool $plannerenabled = true): provider {
        $manager = \core\di::get(\core_ai\manager::class);
        return $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: $name,
            enabled: true,
            config: ['apikey' => $apikey],
            actionconfig: [
                planner_decide::class => [
                    'enabled' => $plannerenabled,
                    'settings' => ['model' => 'm', 'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions'],
                ],
            ],
        );
    }

    /**
     * Resolve the instance a usage request reads.
     *
     * @param int $providerid
     * @return provider|null
     */
    private function resolve(int $providerid): ?provider {
        $method = new \ReflectionMethod(get_usage::class, 'resolve_provider');
        return $method->invoke(null, $providerid);
    }

    /**
     * An explicit id always reads that instance, whatever the provider order says.
     */
    public function test_explicit_id_reads_that_instance(): void {
        $first = $this->create_instance('first', 'key-first');
        $second = $this->create_instance('second', 'key-second');
        set_config('provider_order', $second->id . ',' . $first->id, 'core_ai');

        $this->assertSame((int)$first->id, (int)$this->resolve((int)$first->id)->id);
        $this->assertSame('key-first', $this->resolve((int)$first->id)->config['apikey']);
    }

    /**
     * Without an id, the instance core_ai uses for the agent: provider order first, planner action enabled.
     */
    public function test_without_id_reads_the_instance_that_serves_the_agent(): void {
        $first = $this->create_instance('first', 'key-first');
        $second = $this->create_instance('second', 'key-second');
        $noplanner = $this->create_instance('no planner', 'key-noplanner', false);

        set_config('provider_order', $second->id . ',' . $first->id . ',' . $noplanner->id, 'core_ai');
        $this->assertSame('key-second', $this->resolve(0)->config['apikey']);

        set_config('provider_order', $noplanner->id . ',' . $first->id . ',' . $second->id, 'core_ai');
        $this->assertSame('key-first', $this->resolve(0)->config['apikey'], 'an instance without the planner action is skipped');
    }

    /**
     * An instance without a key never answers for another instance: it is skipped, as core_ai skips it.
     */
    public function test_instance_without_key_is_not_resolved_without_id(): void {
        $keyless = $this->create_instance('keyless', '');
        $keyed = $this->create_instance('keyed', 'key-keyed');
        set_config('provider_order', $keyless->id . ',' . $keyed->id, 'core_ai');

        $this->assertSame((int)$keyed->id, (int)$this->resolve(0)->id);
        $this->assertSame(
            'unconfigured',
            get_usage::execute((int)$keyless->id)['error'],
            'the keyless instance itself reports its own state, never another key'
        );
    }

    /**
     * The form of a new instance (no id yet) shows no usage bar and sends no request.
     */
    public function test_new_instance_form_requests_no_usage(): void {
        global $PAGE;
        $this->create_instance('existing', 'key-existing');
        $PAGE->set_url('/ai/configure.php');

        $mform = new \MoodleQuickForm('newinstance', 'post', '');
        hook_listener::set_form_definition_for_aiprovider_wunderbyte(
            new \core_ai\hook\after_ai_provider_form_hook($mform, 'aiprovider_wunderbyte')
        );

        $this->assertTrue($mform->elementExists('usagebar'));
        $this->assertStringNotContainsString('wb-usage-bar', (string)$mform->getElement('usagebar')->toHtml());
        $this->assertStringNotContainsString('usage_bar', $PAGE->requires->get_end_code());
    }

    /**
     * The form of an existing instance loads the bar, which reads that instance's id from the form.
     */
    public function test_existing_instance_form_loads_the_bar(): void {
        global $PAGE;
        $instance = $this->create_instance('existing', 'key-existing');
        $PAGE->set_url('/ai/configure.php', ['id' => $instance->id]);
        $_GET['id'] = (int)$instance->id;

        try {
            $mform = new \MoodleQuickForm('editinstance', 'post', '');
            hook_listener::set_form_definition_for_aiprovider_wunderbyte(
                new \core_ai\hook\after_ai_provider_form_hook($mform, 'aiprovider_wunderbyte')
            );
        } finally {
            unset($_GET['id']);
        }

        $this->assertStringContainsString('wb-usage-bar', (string)$mform->getElement('usagebar')->toHtml());
        $this->assertStringContainsString('usage_bar', $PAGE->requires->get_end_code());
    }
}
