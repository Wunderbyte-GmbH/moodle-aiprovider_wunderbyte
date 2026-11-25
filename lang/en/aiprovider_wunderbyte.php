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
$string['action_generate_content'] = 'Generate content';
$string['action_generate_content_desc'] = 'Creates course chapters, outlines and quiz questions on the content model.';
$string['action_generate_content_help'] = 'Writes the texts the agent fills courses and question banks with; used only while the agent\'s enhanced content generation is switched on.';
$string['action_generate_embeddings'] = 'Generate embeddings';
$string['action_generate_embeddings_desc'] = 'Generates embedding vectors for task catalog entries and user queries.';
$string['action_generate_embeddings_help'] = 'Creates a vector representation of the provided input text.';
$string['action_generate_embeddings_instruction'] = 'Return a vector embedding for the provided input text.';
$string['action_inspect_image'] = 'Inspect image';
$string['action_inspect_image_desc'] = 'Answers a question about one or more images on a model that can see them.';
$string['action_inspect_image_help'] = 'Lets the agent check whether an image shows what it is meant to show before it proposes or inserts it; the model of this action must accept image input.';
$string['action_planner_construct'] = 'Planner construct';
$string['action_planner_construct_desc'] = 'Builds the parameters of the selected task from the user request.';
$string['action_planner_construct_help'] = 'Fills in the input of the task the planner selected and outputs it as a structured decision.';
$string['action_planner_decide'] = 'Planner decide';
$string['action_planner_decide_desc'] = 'Selects the best task from candidate matches and produces routing decisions.';
$string['action_planner_decide_help'] = 'Chooses the most suitable task candidate and outputs a structured decision.';
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
$string['privacy:metadata:aiprovider_wunderbyte:action'] = 'The name of the AI action that sends the request, so the service can apply the settings of that action.';
$string['privacy:metadata:aiprovider_wunderbyte:externalpurpose'] = 'This information is sent to the Wunderbyte AI service (LiteLLM proxy) in order for a response to be generated. Request and response are logged on the Moodle site for accounting and auditing; the site URL is transmitted with each request so Wunderbyte can attribute the API key to the sites using it (key management and support).';
$string['privacy:metadata:aiprovider_wunderbyte:images'] = 'The images sent with a question about them (action "Inspect image").';
$string['privacy:metadata:aiprovider_wunderbyte:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_wunderbyte:moodle_site'] = 'The URL of this Moodle site (wwwroot), transmitted as request metadata so the API key can be attributed to the sites actually using it.';
$string['privacy:metadata:aiprovider_wunderbyte:prompttext'] = 'The text the response is generated from: the prompt of the request, or the text an embedding is created for. It can contain text a user entered.';
$string['privacy:metadata:aiprovider_wunderbyte:userid'] = 'A hash of the site identifier and the id of the user who triggered the request. It lets the service tell the requests of different users apart without receiving a name or an email address.';
$string['privacy:metadata:field:completiontoken'] = 'The number of tokens of the answer.';
$string['privacy:metadata:field:dimensions'] = 'The number of dimensions of the embedding.';
$string['privacy:metadata:field:embedding'] = 'The embedding the service created for the text, a list of numbers.';
$string['privacy:metadata:field:fingerprint'] = 'The fingerprint of the model configuration, as the service reported it.';
$string['privacy:metadata:field:finishreason'] = 'The reason the model gave for ending its answer.';
$string['privacy:metadata:field:generatedcontent'] = 'The answer of the model.';
$string['privacy:metadata:field:inputtext'] = 'The text an embedding was created for. It can contain text a user entered.';
$string['privacy:metadata:field:model'] = 'The model that created the embedding.';
$string['privacy:metadata:field:prompt'] = 'The prompt sent to the model. It can contain text a user entered and data of the site the request is about.';
$string['privacy:metadata:field:prompttokens'] = 'The number of tokens of the request.';
$string['privacy:metadata:field:responseid'] = 'The identifier the service gave to its answer.';
$string['privacy:metadata:table:generate_agent_reply'] = 'The requests of the action "Generate agent reply" and the answers to them. The AI subsystem of Moodle records which user sent a request.';
$string['privacy:metadata:table:generate_content'] = 'The requests of the action "Generate content" and the answers to them. The AI subsystem of Moodle records which user sent a request.';
$string['privacy:metadata:table:generate_embeddings'] = 'The texts of the action "Generate embeddings" and the embeddings created for them. The AI subsystem of Moodle records which user sent a request.';
$string['privacy:metadata:table:inspect_image'] = 'The questions of the action "Inspect image" and the answers to them; the images are not stored. The AI subsystem of Moodle records which user sent a request.';
$string['privacy:metadata:table:planner_decide'] = 'The requests of the actions "Planner decide" and "Planner construct" and the answers to them. The AI subsystem of Moodle records which user sent a request.';
$string['systeminstruction'] = 'System instruction';
$string['usage_buy_new'] = 'Get new AI credit';
$string['usage_days_left'] = '{$a} days left';
$string['usage_detail_badresponse'] = 'The answer of the usage service could not be read.';
$string['usage_detail_httpstatus'] = 'The usage service answered with HTTP status {$a}.';
$string['usage_detail_noendpoint'] = 'No action of this provider instance has an endpoint, so the address of the usage service is unknown.';
$string['usage_detail_requestfailed'] = 'The request to the usage service failed: {$a}';
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
