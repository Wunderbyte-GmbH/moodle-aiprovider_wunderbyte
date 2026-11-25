## Version 2026112500 (1.0.4)
* Improvement: The tables of the plugin carry the component name: `aiprovider_wunderbyte_<action>` instead of `ai_action_<action>`. The upgrade renames the existing tables; their rows and ids stay, so the log of the AI subsystem keeps pointing to them. After this upgrade an older version of the plugin no longer finds its tables.
* Bugfix: The privacy provider declares the requests and answers the plugin stores for its actions and what a request sends to the AI service, and it exports and deletes the stored requests and answers of a user. Until now it declared the transfer only.
* Improvement: The detail line of the AI credit bar ("usage cannot be read") comes from the language pack.
* Feature: The provider serves Moodle's actions "Summarise text", "Explain text" and "Generate image", so a site can use Moodle's own AI placements with a model of its choice. The two text actions run on the chat model with the instruction Moodle defines for them. Image generation needs a model that creates images behind an OpenAI-compatible images endpoint; it has no default model and is switched off on a new instance until one is entered. On existing instances the upgrade adds the three actions switched off.
* Bugfix: The upgrade adds the actions "Generate content" and "Inspect image" to provider instances that have no entry for them. That was the case on sites where the trial or key setup of the Booking Wizard wrote the instance after the two actions were introduced, so the content model and the reading of images could not be switched on there. An entry that exists is not changed, and an instance that uses a model of its own on another endpoint gets that model.
* Improvement: German language pack. The plugin ships English and German, and a test keeps the two packs in step.
* Improvement: `version.php` names the supported Moodle versions (5.0 to 5.3).

## Version 2026111800 (1.0.3)
* Feature: New action "Inspect image" (`inspect_image`) that sends a text prompt together with one or more images to a model that reads images, with its own model setting per provider instance and its own result table; the stored record keeps prompt and answer, not the image.

## Version 2026101900 (1.0.2)
* Feature: New action "Generate content" (`generate_content`) for the texts the agent creates (course chapters, outlines, quiz questions), with its own endpoint, model and extra parameters per provider instance and its own result table; the default model alias is `wunderbyte-privat-content`. The upgrade adds it to existing instances with their settings of "Generate text". (Wunderbyte-GmbH/Wunderbyte-GmbH#2670)

## Version 2026093001 (1.0.1)
* Improvement: The provider no longer adds a system instruction of its own to chat requests; every caller brings its complete prompt, so moving a caller to another action changes only the model. The settings field stays (empty by default); this upgrade empties the stored instruction of every chat action on every instance, custom texts included. (Wunderbyte-GmbH/Wunderbyte-GmbH#2505)

## Version 2026093000 (1.0.1)
* Feature: New action "Planner construct" (`planner_construct`) for the parameter construction phase of the agent, with its own model and extra parameters per provider instance; stored in the planner_decide table. The upgrade adds it to existing instances with the settings of "Planner decide" and the model of "Generate agent reply"; an existing entry is never changed. New instances default to `wunderbyte-privat-mini` for "Planner decide" and `wunderbyte-privat` for every other chat action. (Wunderbyte-GmbH/Wunderbyte-GmbH#2505)
* Bugfix: The AI credit bar on the form of a new provider instance showed the credit of another instance's key; it now requests nothing until the instance has a saved key. Without an instance id (e.g. the agent panel) the bar reads the instance core_ai actually uses for the agent, following the provider order, instead of the first instance in the database. (Wunderbyte-GmbH/Wunderbyte-GmbH#2506)

## Version 2026082001
* Bugfix: New provider instances defaulted the embeddings action to 1536 dimensions although the default model `wunderbyte-embeddings` (bge-multilingual-gemma2 upstream) returns 3584-dim vectors — consumers validating the vector length against the declared dimensions (e.g. the agent skill catalog) then discarded every stored vector. The default is now 3584; existing instances are unaffected and must be corrected manually. (Wunderbyte-GmbH/Wunderbyte-GmbH#2225)

## Version 2026082000
* Bugfix: The action settings forms defaulted to OpenAI model names (`gpt-4o` for chat actions, `text-embedding-3-small` for embeddings) although the provider's default endpoint is the Wunderbyte LLM proxy. New provider instances now default to the Wunderbyte aliases `wunderbyte-privat` (chat) and `wunderbyte-embeddings` (embeddings, 1536 dimensions). Existing instances are unaffected. (Wunderbyte-GmbH/Wunderbyte-GmbH#2216)

## Version 2026081200
* Bugfix: On Moodle 5.0 every AI call failed with "Unknown named parameter $error", because the custom action response classes passed the error name to core's response_base constructor whose $error parameter only exists from Moodle 5.1 on — the throw happened even for successful provider calls, so the agent always fell back to its generic error reply. The parameter is now only passed on cores that support it; on Moodle 5.0 the error name is carried in errormessage instead. (Wunderbyte-GmbH/Wunderbyte-GmbH#2187)

## Version 2026080602
* Feature: Every request the provider sends to the LiteLLM proxy now carries `metadata: {moodle_site: wwwroot}` (central injection in `abstract_processor::query_ai_api`, covers all actions), so the proxy side can attribute API keys to the sites actually using them without any additional calls from customer systems. Documented in the README ("Data & privacy") and declared in the privacy provider. (Wunderbyte-GmbH/Wunderbyte-GmbH#2167)
* Improvement: The plugin now requires Moodle 5.0.0 (build 2025041400, the first 5.0 release) as its minimum core version. (Wunderbyte-GmbH/Wunderbyte-GmbH#2186)

## Version 2026080601
* Improvement: The AI credit usage bar now surfaces the key expiry: an expired key is shown as "Expired" (danger badge with the expiry date) instead of a remaining percentage — in the full bar and in the compact pill —, the admin view shows the expiry date alongside the reset label, and a warning "X days left" badge appears next to the credit once a fixed expiry is closer than 30 days. (Wunderbyte-GmbH/Wunderbyte-GmbH#2162)
* Improvement: On an expired key the usage bar offers a "Get new AI credit" purchase link. The link URL comes from the Wunderbyte usage gateway (never hardcoded in the plugin), so it only ever appears for keys issued by the Wunderbyte service and can be changed server-side at any time. (Wunderbyte-GmbH/Wunderbyte-GmbH#2163)
