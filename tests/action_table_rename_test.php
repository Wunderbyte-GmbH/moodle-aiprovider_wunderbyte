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
use aiprovider_wunderbyte\aiactions\responses\response_planner_decide;
use aiprovider_wunderbyte\local\action_table_renamer;
use aiprovider_wunderbyte\local\action_tables;

/**
 * The upgrade moves the action tables of an existing site to their new names and loses nothing.
 *
 * Until release 1.0.3 the tables were named ai_action_<action>. A site that has the plugin installed holds its
 * calls in these tables, and ai_action_register points to their rows by id. The upgrade renames the tables; the
 * tests check that rows and ids stay, that new rows continue the ids, that a second run changes nothing, and that
 * the old name is free afterwards.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\local\action_table_renamer
 * @covers     \aiprovider_wunderbyte\aiactions\provider_table
 */
final class action_table_rename_test extends \advanced_testcase {
    /** @var string A table name of the old kind that no plugin uses. */
    private const SCRATCH_OLD = 'ai_action_wbrenametest';

    /** @var string The new name of the scratch table. */
    private const SCRATCH_NEW = 'aiprovider_wunderbyte_wbrenametest';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    #[\Override]
    protected function tearDown(): void {
        global $DB;

        $dbman = $DB->get_manager();
        // The scratch tables of a test go, whatever the test did.
        foreach ([self::SCRATCH_OLD, self::SCRATCH_NEW] as $name) {
            if ($dbman->table_exists($name)) {
                $dbman->drop_table(new \xmldb_table($name));
            }
        }
        // A test that put the plugin's own tables back under their old names leaves them under the new ones.
        foreach (action_table_renamer::legacy_names() as $oldname => $newname) {
            if ($dbman->table_exists($oldname) && !$dbman->table_exists($newname)) {
                $dbman->rename_table(new \xmldb_table($oldname), $newname);
            }
        }
        parent::tearDown();
    }

    /**
     * Create a table with the columns of a chat action table.
     *
     * @param string $name
     */
    private function create_table(string $name): void {
        global $DB;

        $table = new \xmldb_table($name);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('prompt', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('generatedcontent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $DB->get_manager()->create_table($table);
    }

    /**
     * The rows of a table as prompt by id.
     *
     * @param string $name
     * @return string[]
     */
    private function rows(string $name): array {
        global $DB;
        return array_map('strval', $DB->get_records_menu($name, null, 'id', 'id, prompt'));
    }

    /**
     * The list of old names fits the schema: every new name is a table of db/install.xml, every old name is the
     * new one with the prefix of the AI subsystem, and a site installed from the schema has no table of an old name.
     */
    public function test_the_old_names_belong_to_tables_of_the_schema(): void {
        global $DB;

        $installxml = \core_component::get_plugin_directory('aiprovider', 'wunderbyte') . '/db/install.xml';
        $schematables = [];
        foreach (simplexml_load_file($installxml)->TABLES->TABLE as $table) {
            $schematables[] = (string)$table['NAME'];
        }

        $names = action_table_renamer::legacy_names();
        $this->assertNotEmpty($names);
        foreach ($names as $oldname => $newname) {
            $this->assertContains($newname, $schematables);
            $this->assertStringStartsWith('aiprovider_wunderbyte_', $newname);
            $this->assertSame('ai_action_' . substr($newname, strlen('aiprovider_wunderbyte_')), $oldname);
            $this->assertFalse($DB->get_manager()->table_exists($oldname), "$oldname exists on a new site");
            $this->assertTrue($DB->get_manager()->table_exists($newname), "$newname is missing on a new site");
        }
        // Every table of the plugin carries the component name.
        foreach ($schematables as $table) {
            $this->assertStringStartsWith('aiprovider_wunderbyte_', $table);
        }
    }

    /**
     * A renamed table keeps its rows under their ids, and the next row continues the ids.
     */
    public function test_a_renamed_table_keeps_rows_and_ids(): void {
        global $DB;

        $this->create_table(self::SCRATCH_OLD);
        $first = $DB->insert_record(self::SCRATCH_OLD, (object)['prompt' => 'first', 'generatedcontent' => 'a']);
        $second = $DB->insert_record(self::SCRATCH_OLD, (object)['prompt' => 'second', 'generatedcontent' => 'b']);
        $third = $DB->insert_record(self::SCRATCH_OLD, (object)['prompt' => 'third', 'generatedcontent' => 'c']);
        $DB->delete_records(self::SCRATCH_OLD, ['id' => $second]);
        $before = $this->rows(self::SCRATCH_OLD);

        $result = action_table_renamer::rename([self::SCRATCH_OLD => self::SCRATCH_NEW]);

        $this->assertSame([self::SCRATCH_NEW], $result['renamed']);
        $this->assertFalse($DB->get_manager()->table_exists(self::SCRATCH_OLD));
        $this->assertSame($before, $this->rows(self::SCRATCH_NEW));
        $this->assertSame([$first => 'first', $third => 'third'], $this->rows(self::SCRATCH_NEW));

        $next = $DB->insert_record(self::SCRATCH_NEW, (object)['prompt' => 'after the rename']);
        $this->assertGreaterThan($third, $next, 'the id of a new row continues after the rename');
        $this->assertSame('c', $DB->get_field(self::SCRATCH_NEW, 'generatedcontent', ['id' => $third]));
    }

    /**
     * A second run, as after an interrupted upgrade, changes nothing.
     */
    public function test_a_second_run_changes_nothing(): void {
        global $DB;

        $this->create_table(self::SCRATCH_OLD);
        $DB->insert_record(self::SCRATCH_OLD, (object)['prompt' => 'kept']);

        action_table_renamer::rename([self::SCRATCH_OLD => self::SCRATCH_NEW]);
        $rows = $this->rows(self::SCRATCH_NEW);
        $again = action_table_renamer::rename([self::SCRATCH_OLD => self::SCRATCH_NEW]);

        $this->assertSame([], $again['renamed']);
        $this->assertSame([self::SCRATCH_NEW], $again['unchanged']);
        $this->assertSame($rows, $this->rows(self::SCRATCH_NEW));
    }

    /**
     * When both names exist, neither table is touched.
     */
    public function test_two_tables_are_both_left_alone(): void {
        global $DB;

        $this->create_table(self::SCRATCH_OLD);
        $this->create_table(self::SCRATCH_NEW);
        $DB->insert_record(self::SCRATCH_OLD, (object)['prompt' => 'in the old one']);
        $DB->insert_record(self::SCRATCH_NEW, (object)['prompt' => 'in the new one']);

        $result = action_table_renamer::rename([self::SCRATCH_OLD => self::SCRATCH_NEW]);

        $this->assertSame([self::SCRATCH_NEW], $result['both']);
        $this->assertSame([], $result['renamed']);
        $this->assertSame(['in the old one'], array_values($this->rows(self::SCRATCH_OLD)));
        $this->assertSame(['in the new one'], array_values($this->rows(self::SCRATCH_NEW)));
    }

    /**
     * After the rename the old name is free: a table of that name can be created, as a later Moodle version
     * would do for an action of its own, and both tables take rows.
     */
    public function test_the_old_name_is_free_after_the_rename(): void {
        global $DB;

        $this->create_table(self::SCRATCH_OLD);
        $DB->insert_record(self::SCRATCH_OLD, (object)['prompt' => 'ours']);
        action_table_renamer::rename([self::SCRATCH_OLD => self::SCRATCH_NEW]);

        $this->create_table(self::SCRATCH_OLD);
        $DB->insert_record(self::SCRATCH_OLD, (object)['prompt' => 'of the other table']);
        $DB->insert_record(self::SCRATCH_NEW, (object)['prompt' => 'ours again']);

        $this->assertSame(['of the other table'], array_values($this->rows(self::SCRATCH_OLD)));
        $this->assertSame(['ours', 'ours again'], array_values($this->rows(self::SCRATCH_NEW)));
    }

    /**
     * A site as release 1.0.3 left it: the plugin's tables under their old names, with calls in them and the
     * register pointing to the calls. After the upgrade step the calls are where the actions and the privacy
     * provider look for them, under the same ids, and a new call continues the ids.
     */
    public function test_the_upgrade_step_moves_a_site_of_the_old_names(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $user = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($this->getDataGenerator()->create_course()->id);

        // The state before the upgrade: every table under its old name.
        foreach (action_table_renamer::legacy_names() as $oldname => $newname) {
            $dbman->rename_table(new \xmldb_table($newname), $oldname);
        }
        $oldids = [];
        foreach (array_keys(action_table_renamer::legacy_names()) as $oldname) {
            $record = $oldname === 'ai_action_generate_embeddings'
                ? ['inputtext' => "stored in $oldname", 'embedding' => '[0.5]']
                : ['prompt' => "stored in $oldname", 'generatedcontent' => 'answer'];
            $oldids[$oldname] = $DB->insert_record($oldname, (object)$record);
        }
        $register = [
            'actionname' => 'planner_decide',
            'actionid' => $oldids['ai_action_planner_decide'],
            'success' => 1,
            'userid' => $user->id,
            'contextid' => $context->id,
            'provider' => 'aiprovider_wunderbyte',
            'errorcode' => 0,
            'timecreated' => 1760000000,
            'timecompleted' => 1760000001,
            'model' => 'test-model',
            'prompttokens' => 1,
            'completiontokens' => 1,
            'courseid' => -1,
        ];
        // The register got more columns after Moodle 5.0; fill the ones this Moodle has.
        $DB->insert_record('ai_action_register', (object)array_intersect_key($register, $DB->get_columns('ai_action_register')));

        $result = action_table_renamer::rename_legacy_tables();

        $this->assertEqualsCanonicalizing(array_values(action_table_renamer::legacy_names()), $result['renamed']);
        foreach (action_table_renamer::legacy_names() as $oldname => $newname) {
            $this->assertFalse($dbman->table_exists($oldname), "$oldname is still there");
            $field = $oldname === 'ai_action_generate_embeddings' ? 'inputtext' : 'prompt';
            $this->assertSame(
                "stored in $oldname",
                $DB->get_field($newname, $field, ['id' => $oldids[$oldname]]),
                "the row of $oldname is not under its id in $newname",
            );
        }

        // The privacy provider finds the call of the user through the register.
        $this->assertSame(
            [$context->id],
            array_map('intval', privacy\provider::get_contexts_for_userid($user->id)->get_contextids()),
        );

        // A new call of the action lands in the same table and continues the ids.
        $action = new planner_decide($context->id, (int)$user->id, 'after the upgrade');
        $response = new response_planner_decide(success: true);
        $response->set_response_data(['id' => 'r', 'generatedcontent' => 'answer']);
        $newid = $action->store($response);
        $table = action_tables::by_action()['planner_decide'];
        $this->assertGreaterThan($oldids['ai_action_planner_decide'], $newid);
        $this->assertSame('after the upgrade', $DB->get_field($table, 'prompt', ['id' => $newid]));

        // A second run of the step finds nothing to do.
        $again = action_table_renamer::rename_legacy_tables();
        $this->assertSame([], $again['renamed']);
        $this->assertEqualsCanonicalizing(array_values(action_table_renamer::legacy_names()), $again['unchanged']);
    }

    /**
     * A table that exists under neither name is created from the schema, so the plugin can store its calls.
     */
    public function test_a_missing_table_is_created_from_the_schema(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $missing = 'aiprovider_wunderbyte_inspect_image';
        $dbman->drop_table(new \xmldb_table($missing));
        $this->assertFalse($dbman->table_exists($missing));

        try {
            $result = action_table_renamer::rename_legacy_tables();
        } finally {
            // Whatever happened, the plugin's table is there for the tests that follow.
            if (!$dbman->table_exists($missing)) {
                $installxml = \core_component::get_plugin_directory('aiprovider', 'wunderbyte') . '/db/install.xml';
                $dbman->install_one_table_from_xmldb_file($installxml, $missing);
            }
        }

        $this->assertSame([$missing], $result['created']);
        $this->assertSame([], $result['renamed']);
        $this->assertEqualsCanonicalizing(
            array_merge(['id'], action_tables::fields('inspect_image')),
            array_keys($DB->get_columns($missing)),
        );
    }
}
