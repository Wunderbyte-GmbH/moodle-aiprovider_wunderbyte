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

namespace aiprovider_wunderbyte\privacy;

use aiprovider_wunderbyte\local\action_tables;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\external_location;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * The privacy provider covers the requests and answers the provider's own actions store.
 *
 * The provider keeps one row per call in its action tables; Moodle's AI subsystem links the row to a user and a
 * context in ai_action_register. The subsystem's privacy provider handles its own four action tables only, so this
 * plugin has to declare, find, export and delete the rows of its actions itself.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\privacy\provider
 * @covers     \aiprovider_wunderbyte\local\action_tables
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var int A clock for the register rows, so every row has its own time. */
    private int $clock = 1760000000;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Store one call of an action the way Moodle's AI subsystem does: the row of the action through the action's own
     * store() method, and the row that links it to the user and the context in ai_action_register.
     *
     * @param string $actionname Short action name.
     * @param int $userid
     * @param \context $context
     * @param string $text The prompt, or the text to embed.
     * @param string $answer The answer of the model.
     * @return \stdClass With registerid, actionid and table.
     */
    private function log_call(string $actionname, int $userid, \context $context, string $text, string $answer): \stdClass {
        global $DB;

        $actionclass = '\\aiprovider_wunderbyte\\aiactions\\' . $actionname;
        $responseclass = '\\aiprovider_wunderbyte\\aiactions\\responses\\response_' . $actionname;
        $action = new $actionclass($context->id, $userid, $text);
        $response = new $responseclass(success: true);
        $response->set_response_data([
            'id' => 'response of ' . $answer,
            'fingerprint' => 'fingerprint of ' . $answer,
            'generatedcontent' => $answer,
            'finishreason' => 'stop',
            'prompttokens' => 11,
            'completiontokens' => 7,
            'embedding' => [0.25, 0.5],
            'dimensions' => 2,
            'model' => 'test-model',
        ]);
        $actionid = $action->store($response);

        $this->clock++;
        $register = [
            'actionname' => $actionname,
            'actionid' => $actionid,
            'success' => 1,
            'userid' => $userid,
            'contextid' => $context->id,
            'provider' => 'aiprovider_wunderbyte',
            'errorcode' => 0,
            'timecreated' => $this->clock,
            'timecompleted' => $this->clock,
            'model' => 'test-model',
            'prompttokens' => 11,
            'completiontokens' => 7,
            'courseid' => -1,
        ];
        // The register got more columns after Moodle 5.0; fill the ones this Moodle has.
        $register = array_intersect_key($register, $DB->get_columns('ai_action_register'));

        return (object)[
            'registerid' => $DB->insert_record('ai_action_register', (object)$register),
            'actionid' => $actionid,
            'table' => action_tables::by_action()[$actionname],
            'actionname' => $actionname,
        ];
    }

    /**
     * One call of every action for a user in a context.
     *
     * @param \stdClass $user
     * @param \context $context
     * @param string $mark A word that goes into every text, so the rows of a user can be told apart.
     * @return \stdClass[] The calls, by short action name.
     */
    private function log_every_action(\stdClass $user, \context $context, string $mark): array {
        $calls = [];
        foreach (array_keys(action_tables::by_action()) as $actionname) {
            $calls[$actionname] = $this->log_call(
                $actionname,
                (int)$user->id,
                $context,
                "request $mark $actionname",
                "answer $mark $actionname",
            );
        }
        return $calls;
    }

    /**
     * The stored row of a call.
     *
     * @param \stdClass $call
     * @return \stdClass
     */
    private function row(\stdClass $call): \stdClass {
        global $DB;
        return $DB->get_record($call->table, ['id' => $call->actionid], '*', MUST_EXIST);
    }

    /**
     * Whether the request and the answer of a call are still stored.
     *
     * @param \stdClass $call
     * @return bool
     */
    private function has_content(\stdClass $call): bool {
        $row = $this->row($call);
        foreach (action_tables::content_fields($call->actionname) as $field) {
            if ((string)$row->$field !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether every content column of a call is empty.
     *
     * @param \stdClass $call
     * @return bool
     */
    private function is_emptied(\stdClass $call): bool {
        $row = $this->row($call);
        foreach (action_tables::content_fields($call->actionname) as $field) {
            if ((string)$row->$field !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * The list of tables agrees with the action classes and with db/install.xml, so a new action or a new table
     * cannot be left out of the privacy provider without this test failing.
     */
    public function test_the_table_list_matches_the_actions_and_the_schema(): void {
        global $DB;

        $tables = action_tables::by_action();

        // Every action of the provider's own namespace is listed with the table its store() method writes to.
        $ownactions = array_filter(
            \aiprovider_wunderbyte\provider::get_action_list(),
            static fn(string $class): bool => str_starts_with(ltrim($class, '\\'), 'aiprovider_wunderbyte\\'),
        );
        $this->assertNotEmpty($ownactions);
        foreach ($ownactions as $actionclass) {
            $actionname = $actionclass::get_basename();
            $this->assertArrayHasKey($actionname, $tables, "$actionname is missing in action_tables");
            $action = new $actionclass(\context_system::instance()->id, 2, 'text');
            $method = new \ReflectionMethod($action, 'get_tablename');
            $this->assertSame($method->invoke($action), $tables[$actionname], "table of $actionname");
        }
        $this->assertCount(count($ownactions), $tables, 'action_tables lists an action the provider does not offer');

        // Every table of db/install.xml belongs to an action, and no other table is listed.
        $installxml = \core_component::get_plugin_directory('aiprovider', 'wunderbyte') . '/db/install.xml';
        $schema = simplexml_load_file($installxml);
        $schematables = [];
        foreach ($schema->TABLES->TABLE as $table) {
            $schematables[] = (string)$table['NAME'];
        }
        $this->assertEqualsCanonicalizing($schematables, array_values(array_unique($tables)));

        // The listed columns are the columns of the table, without the id.
        foreach ($tables as $actionname => $table) {
            $columns = array_keys($DB->get_columns($table));
            $this->assertEqualsCanonicalizing(
                array_values(array_diff($columns, ['id'])),
                action_tables::fields($actionname),
                "columns of $table",
            );
            $this->assertEmpty(array_diff(action_tables::content_fields($actionname), $columns), "content columns of $table");
        }
    }

    /**
     * The metadata names every table with every column, and what leaves the site.
     */
    public function test_the_metadata_declares_every_table_and_what_is_sent(): void {
        $collection = provider::get_metadata(new collection('aiprovider_wunderbyte'));
        $strings = get_string_manager();

        $declared = [];
        $external = null;
        foreach ($collection->get_collection() as $item) {
            if ($item instanceof database_table) {
                $declared[$item->get_name()] = $item;
            }
            if ($item instanceof external_location) {
                $external = $item;
            }
            $this->assertTrue($strings->string_exists($item->get_summary(), 'aiprovider_wunderbyte'), $item->get_summary());
            foreach ($item->get_privacy_fields() as $stringid) {
                $this->assertTrue($strings->string_exists($stringid, 'aiprovider_wunderbyte'), $stringid);
            }
        }

        foreach (action_tables::by_action() as $actionname => $table) {
            $this->assertArrayHasKey($table, $declared, "$table is not declared");
            $this->assertEqualsCanonicalizing(
                action_tables::fields($actionname),
                array_keys($declared[$table]->get_privacy_fields()),
                "declared columns of $table",
            );
        }
        $this->assertCount(count(array_unique(action_tables::by_action())), $declared);

        // What a request carries: the text, the images of inspect_image, the hashed user id, the site URL, the action.
        $this->assertNotNull($external);
        $sent = array_keys($external->get_privacy_fields());
        // And for image generation the number, size and quality of the images.
        foreach (['prompttext', 'images', 'imageoptions', 'userid', 'moodle_site', 'action', 'model'] as $field) {
            $this->assertContains($field, $sent, "$field is sent and not declared");
        }
    }

    /**
     * The contexts of a user and the users of a context are found for every action.
     */
    public function test_contexts_and_users_are_found_through_the_action_register(): void {
        $generator = $this->getDataGenerator();
        $alice = $generator->create_user();
        $bob = $generator->create_user();
        $carol = $generator->create_user();
        $courseone = \context_course::instance($generator->create_course()->id);
        $coursetwo = \context_course::instance($generator->create_course()->id);

        foreach (array_keys(action_tables::by_action()) as $actionname) {
            // Each action on its own, so an action that is not looked up cannot hide behind another one.
            $context = \context_course::instance($generator->create_course()->id);
            $this->log_call($actionname, (int)$alice->id, $context, 'request', 'answer');
            $this->assertEquals(
                [$context->id],
                array_values(array_intersect(provider::get_contexts_for_userid($alice->id)->get_contextids(), [$context->id])),
                "context of $actionname",
            );
            $userlist = new userlist($context, 'aiprovider_wunderbyte');
            provider::get_users_in_context($userlist);
            $this->assertEquals([$alice->id], $userlist->get_userids(), "users of $actionname");
        }

        $this->log_every_action($bob, $courseone, 'bob');
        $this->log_every_action($bob, $coursetwo, 'bob');
        $this->log_every_action($carol, $coursetwo, 'carol');

        $this->assertEqualsCanonicalizing(
            [$courseone->id, $coursetwo->id],
            provider::get_contexts_for_userid($bob->id)->get_contextids(),
        );
        $this->assertEqualsCanonicalizing([$coursetwo->id], provider::get_contexts_for_userid($carol->id)->get_contextids());

        $userlist = new userlist($coursetwo, 'aiprovider_wunderbyte');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$bob->id, $carol->id], $userlist->get_userids());
    }

    /**
     * The export of a user holds the calls of that user in the approved contexts, with request and answer.
     */
    public function test_the_export_holds_the_calls_of_the_user(): void {
        $generator = $this->getDataGenerator();
        $alice = $generator->create_user();
        $bob = $generator->create_user();
        $context = \context_course::instance($generator->create_course()->id);
        $othercontext = \context_course::instance($generator->create_course()->id);

        $alicecalls = $this->log_every_action($alice, $context, 'alice');
        $elsewhere = $this->log_every_action($alice, $othercontext, 'elsewhere');
        $bobcalls = $this->log_every_action($bob, $context, 'bob');

        provider::export_user_data(new approved_contextlist($alice, 'aiprovider_wunderbyte', [$context->id]));

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        foreach ($alicecalls as $actionname => $call) {
            $data = $writer->get_related_data(provider::export_subcontext($actionname), provider::export_name($call->registerid));
            $this->assertNotEmpty($data, "no export for $actionname");
            $this->assertSame($actionname, $data->actionname);
            if ($actionname === 'generate_embeddings') {
                $this->assertSame("request alice $actionname", $data->inputtext);
                $this->assertSame('[0.25,0.5]', $data->embedding);
            } else {
                $this->assertSame("request alice $actionname", $data->prompt);
                $this->assertSame("answer alice $actionname", $data->generatedcontent);
            }
            // Nothing of another user in the same context.
            $this->assertEmpty($writer->get_related_data(
                provider::export_subcontext($actionname),
                provider::export_name($bobcalls[$actionname]->registerid),
            ));
        }
        // Nothing from a context that was not approved.
        $otherwriter = writer::with_context($othercontext);
        foreach ($elsewhere as $actionname => $call) {
            $this->assertEmpty($otherwriter->get_related_data(
                provider::export_subcontext($actionname),
                provider::export_name($call->registerid),
            ));
        }
    }

    /**
     * Deleting for one user empties that user's requests and answers in the approved contexts and nothing else.
     */
    public function test_deleting_for_a_user_empties_the_calls_of_that_user_only(): void {
        $generator = $this->getDataGenerator();
        $alice = $generator->create_user();
        $bob = $generator->create_user();
        $context = \context_course::instance($generator->create_course()->id);
        $othercontext = \context_course::instance($generator->create_course()->id);

        $deleted = $this->log_every_action($alice, $context, 'alice');
        $kept = array_merge(
            array_values($this->log_every_action($alice, $othercontext, 'elsewhere')),
            array_values($this->log_every_action($bob, $context, 'bob')),
        );

        provider::delete_data_for_user(new approved_contextlist($alice, 'aiprovider_wunderbyte', [$context->id]));

        foreach ($deleted as $actionname => $call) {
            $this->assertTrue($this->is_emptied($call), "$actionname of the user is still stored");
            // The row stays, with what is no content: the action register of the AI subsystem still points to it.
            $this->assertSame(11, (int)$this->row($call)->prompttokens);
        }
        foreach ($kept as $call) {
            $this->assertTrue($this->has_content($call), "{$call->actionname} of another user or context was emptied");
        }
    }

    /**
     * Deleting for a list of users in a context empties the calls of these users in that context.
     */
    public function test_deleting_for_a_list_of_users(): void {
        $generator = $this->getDataGenerator();
        $alice = $generator->create_user();
        $bob = $generator->create_user();
        $carol = $generator->create_user();
        $context = \context_course::instance($generator->create_course()->id);
        $othercontext = \context_course::instance($generator->create_course()->id);

        $deleted = array_merge(
            array_values($this->log_every_action($alice, $context, 'alice')),
            array_values($this->log_every_action($bob, $context, 'bob')),
        );
        $kept = array_merge(
            array_values($this->log_every_action($carol, $context, 'carol')),
            array_values($this->log_every_action($alice, $othercontext, 'elsewhere')),
        );

        provider::delete_data_for_users(new approved_userlist($context, 'aiprovider_wunderbyte', [$alice->id, $bob->id]));

        foreach ($deleted as $call) {
            $this->assertTrue($this->is_emptied($call), "{$call->actionname} of a listed user is still stored");
        }
        foreach ($kept as $call) {
            $this->assertTrue($this->has_content($call), "{$call->actionname} of another user or context was emptied");
        }
    }

    /**
     * Deleting for a whole context empties the calls of every user in it and leaves other contexts alone.
     */
    public function test_deleting_for_a_whole_context(): void {
        $generator = $this->getDataGenerator();
        $alice = $generator->create_user();
        $bob = $generator->create_user();
        $context = \context_course::instance($generator->create_course()->id);
        $othercontext = \context_course::instance($generator->create_course()->id);

        $deleted = array_merge(
            array_values($this->log_every_action($alice, $context, 'alice')),
            array_values($this->log_every_action($bob, $context, 'bob')),
        );
        $kept = array_values($this->log_every_action($alice, $othercontext, 'elsewhere'));

        provider::delete_data_for_all_users_in_context($context);

        foreach ($deleted as $call) {
            $this->assertTrue($this->is_emptied($call), "{$call->actionname} in the context is still stored");
        }
        foreach ($kept as $call) {
            $this->assertTrue($this->has_content($call), "{$call->actionname} of another context was emptied");
        }
    }

    /**
     * A user without any call has no context, and deleting or exporting for such a user changes nothing.
     */
    public function test_a_user_without_calls(): void {
        $generator = $this->getDataGenerator();
        $alice = $generator->create_user();
        $nobody = $generator->create_user();
        $context = \context_course::instance($generator->create_course()->id);
        $calls = $this->log_every_action($alice, $context, 'alice');

        $this->assertEmpty(provider::get_contexts_for_userid($nobody->id)->get_contextids());

        provider::delete_data_for_user(new approved_contextlist($nobody, 'aiprovider_wunderbyte', [$context->id]));
        provider::export_user_data(new approved_contextlist($nobody, 'aiprovider_wunderbyte', [$context->id]));

        foreach ($calls as $call) {
            $this->assertTrue($this->has_content($call));
        }
        $this->assertFalse(writer::with_context($context)->has_any_data());
    }
}
