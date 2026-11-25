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

namespace aiprovider_wunderbyte\local;

/**
 * The tables in which the actions of this provider keep their requests and answers.
 *
 * Every action of the provider stores one row per call (see the store() method of the action classes); Moodle's AI
 * subsystem links that row to the user and the context in ai_action_register (actionname, actionid). This class is the
 * one place that knows which action writes into which table and which columns hold content: the action classes take
 * their table from it (trait aiactions\provider_table) and the privacy provider reads it. A test compares it with
 * the action classes and with db/install.xml.
 *
 * The tables carry the component name. Until release 1.0.3 they were named ai_action_<action>, like the action
 * tables of the AI subsystem itself; local\action_table_renamer moves existing sites to the names used here.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class action_tables {
    /** @var string[] The table of every action, by the short name of the action as ai_action_register stores it. */
    private const TABLES = [
        'generate_agent_reply' => 'aiprovider_wunderbyte_generate_agent_reply',
        'generate_content' => 'aiprovider_wunderbyte_generate_content',
        'generate_embeddings' => 'aiprovider_wunderbyte_generate_embeddings',
        'inspect_image' => 'aiprovider_wunderbyte_inspect_image',
        'planner_decide' => 'aiprovider_wunderbyte_planner_decide',
        // The constructor phase stores its rows in the table of planner_decide.
        'planner_construct' => 'aiprovider_wunderbyte_planner_decide',
    ];

    /** @var string[] The columns of a chat action table, without the id. */
    private const CHAT_FIELDS = [
        'prompt',
        'responseid',
        'fingerprint',
        'generatedcontent',
        'finishreason',
        'prompttokens',
        'completiontoken',
    ];

    /** @var string[] The columns of the embeddings table, without the id. */
    private const EMBEDDINGS_FIELDS = [
        'inputtext',
        'responseid',
        'model',
        'embedding',
        'dimensions',
        'prompttokens',
    ];

    /** @var string[] The columns of a chat action table that hold what was sent and what came back. */
    private const CHAT_CONTENT_FIELDS = ['prompt', 'responseid', 'fingerprint', 'generatedcontent'];

    /** @var string[] The columns of the embeddings table that hold what was sent and what came back. */
    private const EMBEDDINGS_CONTENT_FIELDS = ['inputtext', 'responseid', 'embedding'];

    /**
     * The table of every action of this provider.
     *
     * @return string[] Table name by short action name.
     */
    public static function by_action(): array {
        return self::TABLES;
    }

    /**
     * The columns of the table of an action, without the id.
     *
     * @param string $actionname Short action name.
     * @return string[]
     */
    public static function fields(string $actionname): array {
        return $actionname === 'generate_embeddings' ? self::EMBEDDINGS_FIELDS : self::CHAT_FIELDS;
    }

    /**
     * The columns that are emptied when the data of a user is deleted: the request and the answer.
     *
     * The token counts, the finish reason and the model stay, as in the action tables of Moodle's AI subsystem.
     *
     * @param string $actionname Short action name.
     * @return string[]
     */
    public static function content_fields(string $actionname): array {
        return $actionname === 'generate_embeddings' ? self::EMBEDDINGS_CONTENT_FIELDS : self::CHAT_CONTENT_FIELDS;
    }
}
