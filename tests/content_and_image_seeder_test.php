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

use aiprovider_wunderbyte\aiactions\generate_agent_reply;
use aiprovider_wunderbyte\aiactions\generate_content;
use aiprovider_wunderbyte\aiactions\generate_embeddings;
use aiprovider_wunderbyte\aiactions\inspect_image;
use aiprovider_wunderbyte\aiactions\planner_construct;
use aiprovider_wunderbyte\aiactions\planner_decide;
use aiprovider_wunderbyte\local\content_and_image_seeder;
use core_ai\aiactions\generate_text;

/**
 * An instance that was written after generate_content and inspect_image came gets the two entries on upgrade.
 *
 * The steps 2026101900 and 2026111800 seeded every instance that existed then. The trial and key setup of the
 * Booking Wizard replaced the whole action configuration with five entries until
 * Wunderbyte-GmbH/Wunderbyte-GmbH#2824, so a site that entered a key after those steps has neither entry, and
 * no later step brought them back.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\local\content_and_image_seeder
 */
final class content_and_image_seeder_test extends \advanced_testcase {
    /** @var string Chat endpoint of the Wunderbyte gateway. */
    private const GATEWAY = 'https://llm.wunderbyte.at/v1/chat/completions';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The action configuration the Wizard's setup wrote before it knew the two actions.
     *
     * @param string $chat Chat endpoint.
     * @param string $model Model of the chat actions.
     * @return array
     */
    private function five_entries(string $chat, string $model): array {
        $chatentry = static fn(string $model, float $temperature): array => [
            'enabled' => true,
            'modelsettings' => [],
            'settings' => ['endpoint' => $chat, 'model' => $model, 'systeminstruction' => '', 'temperature' => $temperature],
        ];
        return [
            generate_embeddings::class => [
                'enabled' => true,
                'modelsettings' => [],
                'settings' => ['endpoint' => str_replace('chat/completions', 'embeddings', $chat), 'model' => 'embeddings'],
            ],
            planner_decide::class => $chatentry($model, 0.0),
            planner_construct::class => $chatentry($model, 0.0),
            generate_agent_reply::class => $chatentry($model, 0.3),
            generate_text::class => $chatentry($model, 0.3),
        ];
    }

    /**
     * Create an instance with an action configuration.
     *
     * @param string $name
     * @param array $actionconfig
     * @return int Instance id.
     */
    private function instance(string $name, array $actionconfig): int {
        $instance = \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: $name,
            enabled: true,
            config: ['apikey' => '123'],
            actionconfig: $actionconfig,
        );
        return (int)$instance->id;
    }

    /**
     * The stored action configuration of an instance, as text and decoded.
     *
     * @param int $id
     * @return array{0: string, 1: array}
     */
    private function stored(int $id): array {
        global $DB;
        $raw = (string)$DB->get_field('ai_providers', 'actionconfig', ['id' => $id]);
        return [$raw, json_decode($raw, true)];
    }

    /**
     * On the Wunderbyte gateway both entries are added with the gateway's models, switched on, and what the
     * instance had stays as it was.
     */
    public function test_an_instance_written_by_the_old_setup_gets_both_entries(): void {
        $id = $this->instance('trial site', $this->five_entries(self::GATEWAY, 'wunderbyte-privat'));
        [, $before] = $this->stored($id);

        $this->assertSame(1, content_and_image_seeder::seed_missing());
        $this->assertSame(0, content_and_image_seeder::seed_missing(), 'a second run changes nothing');

        [, $after] = $this->stored($id);
        foreach ($before as $actionclass => $entry) {
            $this->assertSame($entry, $after[$actionclass], $actionclass);
        }
        $this->assertCount(7, $after);

        $content = $after[generate_content::class];
        $this->assertTrue($content['enabled']);
        $this->assertSame(self::GATEWAY, $content['settings']['endpoint']);
        $this->assertSame('wunderbyte-privat-content', $content['settings']['model']);
        $this->assertSame('', $content['settings']['systeminstruction']);

        $image = $after[inspect_image::class];
        $this->assertTrue($image['enabled']);
        $this->assertSame(self::GATEWAY, $image['settings']['endpoint']);
        $this->assertSame('wunderbyte-privat', $image['settings']['model']);
        $this->assertSame('', $image['settings']['systeminstruction']);

        // Moodle hands both actions to the instance now.
        $manager = \core\di::get(\core_ai\manager::class);
        $this->assertTrue($manager->is_action_available(generate_content::class));
        $this->assertTrue($manager->is_action_available(inspect_image::class));
    }

    /**
     * An instance that talks to another endpoint gets its own model: that endpoint does not know the aliases of
     * the Wunderbyte gateway.
     */
    public function test_on_another_endpoint_the_entries_take_the_model_of_the_instance(): void {
        $chat = 'https://ai.example.org/v1/chat/completions';
        $actionconfig = $this->five_entries($chat, 'model-of-the-site');
        $actionconfig[generate_text::class]['settings']['modelextraparams'] = '{"max_tokens": 900}';
        $id = $this->instance('a site with a model of its own', $actionconfig);

        $this->assertSame(1, content_and_image_seeder::seed_missing());

        [, $after] = $this->stored($id);
        foreach ([generate_content::class, inspect_image::class] as $actionclass) {
            $this->assertTrue($after[$actionclass]['enabled'], $actionclass);
            $this->assertSame($chat, $after[$actionclass]['settings']['endpoint'], $actionclass);
            $this->assertSame('model-of-the-site', $after[$actionclass]['settings']['model'], $actionclass);
            $this->assertSame('{"max_tokens": 900}', $after[$actionclass]['settings']['modelextraparams'], $actionclass);
        }
    }

    /**
     * An entry that exists is never changed, whatever it says; only what is missing is added.
     */
    public function test_an_existing_entry_is_left_alone(): void {
        $complete = $this->five_entries(self::GATEWAY, 'wunderbyte-privat');
        $complete[generate_content::class] = [
            'enabled' => false,
            'settings' => ['endpoint' => self::GATEWAY, 'model' => 'chosen by the administrator'],
        ];
        $complete[inspect_image::class] = [
            'enabled' => false,
            'settings' => ['endpoint' => self::GATEWAY, 'model' => 'another choice'],
        ];
        $completeid = $this->instance('complete', $complete);
        [$completebefore] = $this->stored($completeid);

        $half = $this->five_entries(self::GATEWAY, 'wunderbyte-privat');
        $half[generate_content::class] = $complete[generate_content::class];
        $halfid = $this->instance('content only', $half);

        $this->assertSame(1, content_and_image_seeder::seed_missing());

        [$completeafter] = $this->stored($completeid);
        $this->assertSame($completebefore, $completeafter);

        [, $halfafter] = $this->stored($halfid);
        $this->assertFalse($halfafter[generate_content::class]['enabled']);
        $this->assertSame('chosen by the administrator', $halfafter[generate_content::class]['settings']['model']);
        $this->assertTrue($halfafter[inspect_image::class]['enabled']);
        $this->assertSame('wunderbyte-privat', $halfafter[inspect_image::class]['settings']['model']);
    }

    /**
     * Without an endpoint for generate_text there is nothing to derive an entry from; the instance is left alone.
     */
    public function test_an_instance_without_a_text_endpoint_is_left_alone(): void {
        $id = $this->instance('planner only', [
            planner_decide::class => [
                'enabled' => true,
                'settings' => ['endpoint' => self::GATEWAY, 'model' => 'wunderbyte-privat-mini'],
            ],
        ]);
        [$before] = $this->stored($id);

        $this->assertSame(0, content_and_image_seeder::seed_missing());

        [$after] = $this->stored($id);
        $this->assertSame($before, $after);
    }

    /**
     * The upgrade to 2026112500 runs the seeder: an instance of the old setup has both entries afterwards.
     */
    public function test_the_upgrade_step_adds_the_entries(): void {
        global $CFG;
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/ai/provider/wunderbyte/db/upgrade.php');

        $id = $this->instance('trial site', $this->five_entries(self::GATEWAY, 'wunderbyte-privat'));
        // The site comes from the release before this one.
        set_config('version', 2026111800, 'aiprovider_wunderbyte');

        ob_start();
        try {
            $result = xmldb_aiprovider_wunderbyte_upgrade(2026111800);
        } finally {
            ob_end_clean();
        }

        $this->assertTrue($result);
        [, $after] = $this->stored($id);
        $this->assertArrayHasKey(generate_content::class, $after);
        $this->assertArrayHasKey(inspect_image::class, $after);
        $this->assertSame('wunderbyte-privat-content', $after[generate_content::class]['settings']['model']);
    }
}
