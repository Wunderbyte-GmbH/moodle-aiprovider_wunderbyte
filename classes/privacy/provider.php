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
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider of the Wunderbyte AI provider.
 *
 * The plugin sends requests to the configured AI service and keeps one row per call of its own actions in the table
 * of the action: the request (prompt or text to embed) and the answer. The calls of Moodle's actions it serves
 * (generate_text, summarise_text, explain_text, generate_image) are stored by the AI subsystem in its own tables.
 * A row of this plugin carries no user id of its own. Moodle's AI subsystem
 * links it to the user and the context in ai_action_register (actionname, actionid), and its privacy provider covers
 * that register and the subsystem's own action tables only. The rows of this provider's actions are therefore
 * declared, found, exported and deleted here, through the register.
 *
 * Deleting empties the request and the answer of a row and keeps the row, as the subsystem does for its own action
 * tables: the register still points to it, and the token counts carry no personal data.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  based on work by 2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var int How many ids go into one SQL statement. */
    private const CHUNK = 1000;

    #[\Override]
    public static function get_metadata(collection $collection): collection {
        // What every request carries to the configured AI service.
        $collection->add_external_location_link('aiprovider_wunderbyte', [
            'prompttext' => 'privacy:metadata:aiprovider_wunderbyte:prompttext',
            'images' => 'privacy:metadata:aiprovider_wunderbyte:images',
            'imageoptions' => 'privacy:metadata:aiprovider_wunderbyte:imageoptions',
            'userid' => 'privacy:metadata:aiprovider_wunderbyte:userid',
            'model' => 'privacy:metadata:aiprovider_wunderbyte:model',
            'action' => 'privacy:metadata:aiprovider_wunderbyte:action',
            'moodle_site' => 'privacy:metadata:aiprovider_wunderbyte:moodle_site',
        ], 'privacy:metadata:aiprovider_wunderbyte:externalpurpose');

        // What stays on the site: one table per action; two actions share the planner table.
        $declared = [];
        foreach (action_tables::by_action() as $actionname => $table) {
            if (isset($declared[$table])) {
                continue;
            }
            $declared[$table] = true;
            $fields = [];
            foreach (action_tables::fields($actionname) as $field) {
                $fields[$field] = 'privacy:metadata:field:' . $field;
            }
            $collection->add_database_table($table, $fields, 'privacy:metadata:table:' . $actionname);
        }

        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (action_tables::by_action() as $actionname => $table) {
            $sql = "SELECT aar.contextid
                      FROM {ai_action_register} aar
                      JOIN {" . $table . "} act ON act.id = aar.actionid
                     WHERE aar.actionname = :actionname
                           AND aar.userid = :userid";
            $contextlist->add_from_sql($sql, ['actionname' => $actionname, 'userid' => $userid]);
        }
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        foreach (action_tables::by_action() as $actionname => $table) {
            $sql = "SELECT aar.userid
                      FROM {ai_action_register} aar
                      JOIN {" . $table . "} act ON act.id = aar.actionid
                     WHERE aar.actionname = :actionname
                           AND aar.contextid = :contextid";
            $userlist->add_from_sql('userid', $sql, ['actionname' => $actionname, 'contextid' => $context->id]);
        }
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach (array_chunk($contextlist->get_contextids(), self::CHUNK) as $contextids) {
            [$contextsql, $contextparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
            foreach (action_tables::by_action() as $actionname => $table) {
                $fields = action_tables::fields($actionname);
                $columns = implode(', ', array_map(static fn(string $field): string => 'act.' . $field, $fields));
                $sql = "SELECT aar.id AS registerid, aar.contextid, aar.success, aar.provider, aar.model AS registermodel,
                               aar.timecreated, aar.timecompleted, $columns
                          FROM {ai_action_register} aar
                          JOIN {" . $table . "} act ON act.id = aar.actionid
                         WHERE aar.actionname = :actionname
                               AND aar.userid = :userid
                               AND aar.contextid $contextsql
                      ORDER BY aar.id";
                $params = ['actionname' => $actionname, 'userid' => $userid] + $contextparams;
                $records = $DB->get_recordset_sql($sql, $params);
                foreach ($records as $record) {
                    $details = ['actionname' => $actionname, 'contextid' => $record->contextid];
                    foreach ($fields as $field) {
                        $details[$field] = $record->$field;
                    }
                    // The embeddings table stores the model itself; for the other actions the register has it.
                    $details['model'] = $details['model'] ?? $record->registermodel;
                    $details['success'] = transform::yesno($record->success);
                    $details['provider'] = $record->provider;
                    $details['timecreated'] = transform::datetime($record->timecreated);
                    $details['timecompleted'] = $record->timecompleted ? transform::datetime($record->timecompleted) : null;
                    writer::with_context(\context::instance_by_id($record->contextid))->export_related_data(
                        self::export_subcontext($actionname),
                        self::export_name((int)$record->registerid),
                        (object)$details,
                    );
                }
                $records->close();
            }
        }
    }

    /**
     * The folder of an export: the plugin, then the action.
     *
     * @param string $actionname Short action name.
     * @return string[]
     */
    public static function export_subcontext(string $actionname): array {
        return [
            get_string('pluginname', 'aiprovider_wunderbyte'),
            get_string('action_' . $actionname, 'aiprovider_wunderbyte'),
        ];
    }

    /**
     * The name of one exported call: the id of its row in the action register, which is unique across all actions.
     *
     * @param int $registerid Id of the row in ai_action_register.
     * @return string
     */
    public static function export_name(int $registerid): string {
        return 'call_' . $registerid;
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context) {
        self::empty_calls('aar.contextid = :contextid', ['contextid' => $context->id]);
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $contextid = $userlist->get_context()->id;
        foreach (array_chunk($userlist->get_userids(), self::CHUNK) as $userids) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            self::empty_calls(
                "aar.contextid = :contextid AND aar.userid $usersql",
                ['contextid' => $contextid] + $userparams,
            );
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach (array_chunk($contextlist->get_contextids(), self::CHUNK) as $contextids) {
            [$contextsql, $contextparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
            self::empty_calls(
                "aar.userid = :userid AND aar.contextid $contextsql",
                ['userid' => $userid] + $contextparams,
            );
        }
    }

    /**
     * Empty the request and the answer of the calls the action register lists under a condition, for every action.
     *
     * The rows are selected in a subquery on the register, so the number of calls of a user does not limit the
     * statement (a site can hold tens of thousands of calls of one user).
     *
     * @param string $registerwhere Condition on the register row (alias aar), with named parameters.
     * @param array $params The parameters of the condition.
     */
    private static function empty_calls(string $registerwhere, array $params): void {
        global $DB;

        foreach (action_tables::by_action() as $actionname => $table) {
            $assignments = implode(', ', array_map(
                static fn(string $field): string => $field . " = ''",
                action_tables::content_fields($actionname),
            ));
            $sql = "UPDATE {" . $table . "}
                       SET $assignments
                     WHERE id IN (SELECT aar.actionid
                                    FROM {ai_action_register} aar
                                   WHERE aar.actionname = :actionname
                                         AND $registerwhere)";
            $DB->execute($sql, ['actionname' => $actionname] + $params);
        }
    }
}
