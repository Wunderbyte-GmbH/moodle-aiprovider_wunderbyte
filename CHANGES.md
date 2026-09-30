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
