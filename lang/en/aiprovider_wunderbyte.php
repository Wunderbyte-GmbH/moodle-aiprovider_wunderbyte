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
 * Language strings for the Wunderbyte AI provider.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action_generate_agent_reply'] = 'Generate agent reply';
$string['action_generate_agent_reply_desc'] = 'Generates the final user-facing agent reply.';
$string['action_generate_agent_reply_help'] = 'Composes the final assistant response from the accumulated task output.';
$string['action_generate_agent_reply_instruction'] = 'Compose the final user-facing response in the requested language.';
$string['action_generate_embeddings'] = 'Generate embeddings';
$string['action_generate_embeddings_desc'] = 'Generates embedding vectors for task catalog entries and user queries.';
$string['action_generate_embeddings_help'] = 'Creates a vector representation of the provided input text.';
$string['action_generate_embeddings_instruction'] = 'Return a vector embedding for the provided input text.';
$string['action_planner_decide'] = 'Planner decide';
$string['action_planner_decide_desc'] = 'Selects the best task from candidate matches and produces routing decisions.';
$string['action_planner_decide_help'] = 'Chooses the most suitable task candidate and outputs a structured decision.';
$string['action_planner_decide_instruction'] = 'Act as a compact planner and return a structured routing decision as plain JSON.';
$string['apikey'] = 'API key';
$string['apikey_help'] = 'API key for the Wunderbyte OpenAI-compatible endpoint.';
$string['dimensions'] = 'Embedding dimensions';
$string['endpoint'] = 'API endpoint';
$string['model'] = 'Model';
$string['pluginname'] = 'Wunderbyte AI provider';

$string['systeminstruction'] = 'System instruction';
