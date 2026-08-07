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
$string['action_generate_text_instruction'] = 'Follow the user instructions and return only the requested content.';
$string['action_planner_decide'] = 'Planner decide';
$string['action_planner_decide_desc'] = 'Selects the best task from candidate matches and produces routing decisions.';
$string['action_planner_decide_help'] = 'Chooses the most suitable task candidate and outputs a structured decision.';
$string['action_planner_decide_instruction'] = 'Act as a compact planner and return a structured routing decision as plain JSON.';
$string['apikey'] = 'API key';
$string['apikey_help'] = 'API key for the Wunderbyte OpenAI-compatible endpoint.';
$string['dimensions'] = 'Embedding dimensions';
$string['endpoint'] = 'API endpoint';
$string['error:busy'] = 'The AI service is currently very busy. Please try again in a few minutes.';
$string['extraparams'] = 'Extra parameters';
$string['extraparams_help'] = 'Optional extra request parameters as JSON, merged verbatim into the request body sent to the model. Use this to enforce a hard output limit, for example: {"max_tokens": 500}. Newer models may require {"max_completion_tokens": 500} instead.';
$string['invalidjson'] = 'Invalid JSON string';
$string['model'] = 'Model';
$string['pluginname'] = 'Wunderbyte AI provider';
$string['privacy:metadata'] = 'The Wunderbyte AI provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_wunderbyte:externalpurpose'] = 'This information is sent to the Wunderbyte AI service (LiteLLM proxy) in order for a response to be generated. Request and response are logged on the Moodle site for accounting and auditing; the site URL is transmitted with each request so Wunderbyte can attribute the API key to the sites using it (key management and support).';
$string['privacy:metadata:aiprovider_wunderbyte:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_wunderbyte:moodle_site'] = 'The URL of this Moodle site (wwwroot), transmitted as request metadata so the API key can be attributed to the sites actually using it.';
$string['privacy:metadata:aiprovider_wunderbyte:numberimages'] = 'When generating images the number of images used in the response.';
$string['privacy:metadata:aiprovider_wunderbyte:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_wunderbyte:responseformat'] = 'The format of the response. When generating images.';

$string['systeminstruction'] = 'System instruction';

$string['usage_buy_new'] = 'Get new AI credit';
$string['usage_days_left'] = '{$a} days left';
$string['usage_expired'] = 'Expired';
$string['usage_expired_on'] = 'Key expired on {$a}';
$string['usage_expires'] = 'Key expires {$a}';
$string['usage_heading'] = 'AI credit';
$string['usage_left'] = '{$a} left';
$string['usage_percent_remaining'] = '{$a}% left';
$string['usage_percent_used'] = '{$a}% used';
$string['usage_remaining'] = '{$a->remaining} of {$a->total} remaining';
$string['usage_reset_days'] = 'Resets in {$a} days';
$string['usage_reset_today'] = 'Resets today';
$string['usage_reset_tomorrow'] = 'Resets tomorrow';
$string['usage_spent'] = '{$a->spend} used';
$string['usage_unavailable'] = 'Usage information is currently unavailable.';
$string['usage_unconfigured'] = 'Add an API key to see your remaining AI credit.';
$string['usage_unlimited'] = 'Unlimited';
$string['usage_unlimited_detail'] = 'No spending limit on this key.';
$string['wunderbyte:viewusage'] = 'View AI credit usage for the Wunderbyte provider';
