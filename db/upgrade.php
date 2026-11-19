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

    if ($oldversion < 2026101900) {
        global $DB;
        $dbman = $DB->get_manager();

        // Content creation gets its own action (generate_content) on the content model, with its own result table.
        $table = new xmldb_table('ai_action_generate_content');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('responseid', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('fingerprint', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('generatedcontent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('finishreason', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('prompttokens', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('completiontoken', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Existing instances receive the entry; instances that already have one are left alone.
        \aiprovider_wunderbyte\local\generate_content_seeder::seed_missing();

        upgrade_plugin_savepoint(true, 2026101900, 'aiprovider', 'wunderbyte');
    }

    if ($oldversion < 2026111800) {
        global $DB;
        $dbman = $DB->get_manager();
        // Questions about images get their own action (inspect_image) with its own result table.
        $table = new xmldb_table('ai_action_inspect_image');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('responseid', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('fingerprint', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('generatedcontent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('finishreason', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('prompttokens', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('completiontoken', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        // Existing instances receive the entry; instances that already have one are left alone.
        \aiprovider_wunderbyte\local\inspect_image_seeder::seed_missing();
        upgrade_plugin_savepoint(true, 2026111800, 'aiprovider', 'wunderbyte');
    }

    return true;
}
