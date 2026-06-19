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

/**
 * Uninstall cleanup for aiprovider_wunderbyte.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove core_ai state that Moodle does NOT clean up when this plugin is uninstalled.
 *
 * Moodle drops this plugin's own tables (ai_action_generate_embeddings /
 * ai_action_planner_decide / ai_action_generate_agent_reply), but core_ai's shared
 * ai_action_register keeps the rows for our custom actions. After a reinstall the
 * recreated (empty) action tables restart their ids from 1 and collide with those
 * stale register rows on the UNIQUE (actionname, actionid) index — surfacing as
 * "Error writing to database" on every agent call. We also drop our now-orphaned
 * provider instances and prune them from core_ai's provider order so the AI
 * providers page does not reference dead instance ids afterwards.
 *
 * @return bool
 */
function xmldb_aiprovider_wunderbyte_uninstall() {
    global $DB;

    $dbman = $DB->get_manager();

    // Our custom AI actions, by the short name stored in ai_action_register.
    if ($dbman->table_exists('ai_action_register')) {
        $actionnames = ['generate_embeddings', 'planner_decide', 'generate_agent_reply'];
        foreach ($actionnames as $name) {
            $DB->delete_records('ai_action_register', ['actionname' => $name]);
        }
    }

    // Drop our provider instances and prune them from core_ai's provider order.
    if ($dbman->table_exists('ai_providers')) {
        $instanceids = $DB->get_fieldset_select(
            'ai_providers',
            'id',
            $DB->sql_like('provider', ':p'),
            ['p' => '%aiprovider_wunderbyte%']
        );
        if (!empty($instanceids)) {
            $DB->delete_records_list('ai_providers', 'id', $instanceids);

            $order = get_config('core_ai', 'provider_order');
            if ($order !== false && $order !== '') {
                $kept = array_values(array_filter(
                    explode(',', (string) $order),
                    static function ($value) use ($instanceids): bool {
                        $value = trim($value);
                        return $value !== '' && !in_array((int) $value, $instanceids, true);
                    }
                ));
                set_config('provider_order', implode(',', $kept), 'core_ai');
            }
        }
    }

    return true;
}
