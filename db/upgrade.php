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
 * Upgrade steps for aiprovider_wunderbyte.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The version the plugin is upgraded from.
 * @return bool
 */
function xmldb_aiprovider_wunderbyte_upgrade($oldversion) {
    if ($oldversion < 2026093000) {
        // The constructor phase of the agent gets its own action (planner_construct) on the large model.
        // Existing instances receive the entry; instances that already have one are left alone.
        \aiprovider_wunderbyte\local\planner_construct_seeder::seed_missing();

        upgrade_plugin_savepoint(true, 2026093000, 'aiprovider', 'wunderbyte');
    }

    if ($oldversion < 2026093001) {
        // The provider sends no instruction of its own any more: every caller brings its complete prompt. Empty the
        // stored system instruction of every chat action, custom texts included (George 2026-09-30).
        \aiprovider_wunderbyte\local\system_instruction_cleaner::clear_all();

        upgrade_plugin_savepoint(true, 2026093001, 'aiprovider', 'wunderbyte');
    }

    return true;
}
