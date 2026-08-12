## Version 2026080602
* Feature: Every request the provider sends to the LiteLLM proxy now carries `metadata: {moodle_site: wwwroot}` (central injection in `abstract_processor::query_ai_api`, covers all actions), so the proxy side can attribute API keys to the sites actually using them without any additional calls from customer systems. Documented in the README ("Data & privacy") and declared in the privacy provider. (Wunderbyte-GmbH/Wunderbyte-GmbH#2167)
* Improvement: The plugin now requires Moodle 5.0.0 (build 2025041400, the first 5.0 release) as its minimum core version. (Wunderbyte-GmbH/Wunderbyte-GmbH#2186)

## Version 2026080601
* Improvement: The AI credit usage bar now surfaces the key expiry: an expired key is shown as "Expired" (danger badge with the expiry date) instead of a remaining percentage — in the full bar and in the compact pill —, the admin view shows the expiry date alongside the reset label, and a warning "X days left" badge appears next to the credit once a fixed expiry is closer than 30 days. (Wunderbyte-GmbH/Wunderbyte-GmbH#2162)
* Improvement: On an expired key the usage bar offers a "Get new AI credit" purchase link. The link URL comes from the Wunderbyte usage gateway (never hardcoded in the plugin), so it only ever appears for keys issued by the Wunderbyte service and can be changed server-side at any time. (Wunderbyte-GmbH/Wunderbyte-GmbH#2163)
