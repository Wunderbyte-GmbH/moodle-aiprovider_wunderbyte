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

namespace aiprovider_wunderbyte\local;

use aiprovider_wunderbyte\aiactions\generate_agent_reply;
use aiprovider_wunderbyte\aiactions\planner_construct;
use aiprovider_wunderbyte\aiactions\planner_decide;
use aiprovider_wunderbyte\form\action_chat_form;

/**
 * Adds the planner_construct action to provider instances created before the action existed.
 *
 * The new entry copies the planner_decide settings (endpoint, extra parameters, enabled state), takes the model of
 * generate_agent_reply - the instance's large model - and the default construct instruction. Instances that already
 * carry a planner_construct entry are never changed, so an admin's configuration survives every upgrade.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner_construct_seeder {
    /**
     * Seed every Wunderbyte provider instance that has no planner_construct entry yet.
     *
     * @return int Number of instances changed.
     */
    public static function seed_missing(): int {
        global $DB;

        $changed = 0;
        $records = $DB->get_records('ai_providers', ['provider' => 'aiprovider_wunderbyte\\provider']);
        foreach ($records as $record) {
            $actionconfig = json_decode((string)($record->actionconfig ?? ''), true);
            if (!is_array($actionconfig) || array_key_exists(planner_construct::class, $actionconfig)) {
                continue;
            }
            $actionconfig[planner_construct::class] = self::build_entry($actionconfig);
            $record->actionconfig = json_encode($actionconfig);
            $DB->update_record('ai_providers', $record);
            $changed++;
        }

        return $changed;
    }

    /**
     * Build the planner_construct entry from an instance's existing action configuration.
     *
     * @param array $actionconfig Decoded action configuration of one instance.
     * @return array
     */
    public static function build_entry(array $actionconfig): array {
        $decide = (array)($actionconfig[planner_decide::class] ?? []);
        $replysettings = (array)($actionconfig[generate_agent_reply::class]['settings'] ?? []);

        $settings = (array)($decide['settings'] ?? []);
        if (($settings['endpoint'] ?? '') === '' && ($replysettings['endpoint'] ?? '') !== '') {
            $settings['endpoint'] = $replysettings['endpoint'];
        }
        $settings['model'] = (string)($replysettings['model'] ?? '') !== ''
            ? (string)$replysettings['model']
            : action_chat_form::default_model('planner_construct');
        $settings['systeminstruction'] = planner_construct::get_system_instruction();

        return [
            'enabled' => array_key_exists('enabled', $decide) ? (bool)$decide['enabled'] : true,
            'settings' => $settings,
        ];
    }
}
