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
use aiprovider_wunderbyte\local\planner_construct_seeder;

/**
 * The upgrade adds the planner_construct action to provider instances created before it existed.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\local\planner_construct_seeder
 */
final class planner_construct_upgrade_test extends \advanced_testcase {
    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * The upgrade adds planner_construct with the large model and the planner_decide settings.
     */
    public function test_upgrade_seeds_construct_from_existing_instance(): void {
        global $DB;
        $manager = \core\di::get(\core_ai\manager::class);
        $legacy = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'legacy',
            config: ['apikey' => '123'],
            actionconfig: [
                planner_decide::class => [
                    'enabled' => true,
                    'settings' => [
                        'model' => 'wunderbyte-privat-mini',
                        'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions',
                        'modelextraparams' => '{"max_tokens": 8192}',
                    ],
                ],
                generate_agent_reply::class => [
                    'enabled' => true,
                    'settings' => ['model' => 'wunderbyte-privat', 'endpoint' => 'https://llm.wunderbyte.at/v1/chat/completions'],
                ],
            ],
        );
        $disabled = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'disabled planner',
            config: ['apikey' => '456'],
            actionconfig: [
                planner_decide::class => ['enabled' => false, 'settings' => ['model' => 'm', 'endpoint' => 'https://e']],
                generate_agent_reply::class => ['enabled' => true, 'settings' => ['model' => 'big', 'endpoint' => 'https://e']],
            ],
        );
        $configured = $manager->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'tiers',
            config: ['apikey' => '789'],
            actionconfig: [
                planner_decide::class => ['enabled' => true, 'settings' => ['model' => 'small', 'endpoint' => 'https://e']],
                planner_construct::class => [
                    'enabled' => true,
                    'settings' => ['model' => 'admin-choice', 'endpoint' => 'https://e'],
                ],
            ],
        );
        $configuredbefore = $DB->get_field('ai_providers', 'actionconfig', ['id' => $configured->id]);

        $changed = planner_construct_seeder::seed_missing();
        $again = planner_construct_seeder::seed_missing();

        $this->assertSame(2, $changed);
        $this->assertSame(0, $again, 'the upgrade step is idempotent');
        $read = static fn(int $id): array => json_decode(
            (string)$DB->get_field('ai_providers', 'actionconfig', ['id' => $id]),
            true
        )[planner_construct::class];

        $seeded = $read((int)$legacy->id);
        $this->assertTrue($seeded['enabled']);
        $this->assertSame('wunderbyte-privat', $seeded['settings']['model']);
        $this->assertSame('https://llm.wunderbyte.at/v1/chat/completions', $seeded['settings']['endpoint']);
        $this->assertSame('{"max_tokens": 8192}', $seeded['settings']['modelextraparams']);
        $this->assertSame('', $seeded['settings']['systeminstruction']);

        $this->assertFalse($read((int)$disabled->id)['enabled'], 'a disabled selector action stays disabled');
        $this->assertSame('big', $read((int)$disabled->id)['settings']['model']);

        $this->assertSame(
            $configuredbefore,
            $DB->get_field('ai_providers', 'actionconfig', ['id' => $configured->id]),
            'an existing planner_construct entry is never changed'
        );
    }
}
