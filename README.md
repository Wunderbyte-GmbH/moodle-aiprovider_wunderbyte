# Wunderbyte AI provider (`aiprovider_wunderbyte`)

A provider for Moodle's AI subsystem (`core_ai`) that connects Moodle to an OpenAI-compatible
AI service. Its default settings point to the Wunderbyte AI service (`https://llm.wunderbyte.at`,
a [LiteLLM](https://github.com/BerriAI/litellm) proxy).

It serves Moodle's own actions (generate, summarise and explain text, generate image) and brings
the actions the Wunderbyte AI Wizard (`local_wizard`, and the agent inside the Booking plugin)
works with: planning, embeddings, the final reply, content creation and questions about images.
A bar on the provider form shows administrators how much of the AI credit of a key is used.

> Plugin location: `ai/provider/wunderbyte/` · Component: `aiprovider_wunderbyte` ·
> Licence: GNU GPL v3 or later · Requires Moodle 5.0 or later (`2025041400`)

## What you need

* Moodle 5.0 or later.
* An API key for the AI service the actions point to. For the Wunderbyte AI service the key comes
  with a Wunderbyte subscription, or as a one-time trial key that the AI Wizard plugin
  (`local_wizard`) requests from inside Moodle.
* No other plugin is needed.

## Actions

| Action | Request | Purpose |
|---|---|---|
| `generate_text` (Moodle core) | chat | Text generation for Moodle's own placements. |
| `summarise_text` (Moodle core) | chat | Summarises the text a placement sends. |
| `explain_text` (Moodle core) | chat | Explains the text a placement sends. |
| `generate_image` (Moodle core) | images | Creates an image from a prompt. Needs a model that creates images, see below. |
| `planner_decide` | chat | Selects the task that fits a request and returns a structured decision. |
| `planner_construct` | chat | Builds the parameters of the selected task. Stored in the table of `planner_decide`. |
| `generate_agent_reply` | chat | Writes the final answer the user reads. |
| `generate_content` | chat | Writes course chapters, outlines and quiz questions. |
| `inspect_image` | chat with images | Answers a question about one or more images. Needs a model that accepts image input. |
| `generate_embeddings` | embeddings | Creates the vector of a text, for the task catalogue and for searches. |

Every action is enabled and configured per provider instance, with its own endpoint and model.
The provider adds no system instruction of its own: the caller brings the complete prompt, and the
field "System instruction" of an action is empty by default. The exception are `summarise_text`
and `explain_text`: Moodle's placements send the text alone, so their field starts with the
instruction Moodle defines for the action.

**Image generation** is switched off on a new instance and has no default model. A site with a
model that creates images behind an OpenAI-compatible images endpoint enters endpoint and model
and switches the action on. The provider sends the model, the
prompt, one image, a size for the chosen aspect ratio (`1024x1024`, `1536x1024`, `1024x1536`) and a
quality (`medium`, `high`); the extra parameters of the action replace any of these, and `null`
leaves one out. The answer may carry the image as base64 data or as a URL.

**After an upgrade** from a release before 1.0.4, `summarise_text`, `explain_text` and
`generate_image` are switched off on the existing instances. The two text actions start with the
endpoint and model of the instance's `generate_text`.

## How a request is sent

```
core_ai action  ->  process_<action>  ->  abstract_processor  ->  endpoint of the action
```

* `abstract_processor` adds the header `Authorization: Bearer <API key>`, sends the request with
  Moodle's `core\http_client` (so the proxy and TLS settings of the site apply) and turns errors
  into the error format of `core_ai`. A busy service (HTTP 429 or 503) is answered with one
  translated message.
* `process_chat_action` builds a `/chat/completions` request for the chat actions;
  `process_inspect_image` adds the images as content parts; `process_generate_embeddings` calls
  the embeddings endpoint; `process_generate_image` calls the images endpoint and stores the image
  as a draft file with Moodle's watermark for AI images.
* Every request carries a hash of the site identifier and the user id (`user`), the URL of the
  site (`metadata.moodle_site`) and the name of the action (`metadata.action`). See "Data and
  privacy" below.

## Configuration

**Site administration > General > AI > AI providers**, then create an instance of
"Wunderbyte AI provider".

| Setting | Where | Notes |
|---|---|---|
| API key | Provider instance | Required. Sent as `Bearer` token to the endpoints of the actions, and to the usage service of the same host for the AI credit bar. |
| API endpoint | Per action | Default `https://llm.wunderbyte.at/v1/chat/completions`, for embeddings `https://llm.wunderbyte.at/v1/embeddings`, for images `https://llm.wunderbyte.at/v1/images/generations`. |
| Model | Per action | Defaults: `wunderbyte-privat-mini` for `planner_decide`, `wunderbyte-privat-content` for `generate_content`, `wunderbyte-embeddings` for embeddings, `wunderbyte-privat` for the other chat actions, none for `generate_image`. |
| System instruction | Per chat action | Empty by default; for `summarise_text` and `explain_text` the instruction Moodle defines. |
| Extra parameters | Per chat action and image generation | Optional JSON that is merged into the request body, for example `{"max_tokens": 500}`. |
| Embedding dimensions | Embeddings action | Default 3584, the size the default model returns. |

The provider counts as configured as soon as an API key is entered.

## AI credit bar

* The form of a provider instance shows how much of the credit of its key is used, when the credit
  resets and when the key expires. An expired key shows a link for new credit when the service
  sends one.
* The plugin asks the usage service of the configured host (`POST /api/shop/usage`) with the key of
  the instance. The service answers with a percentage and dates, never with amounts. This call is
  no AI action and uses no tokens.
* Who sees it: users with the capability `aiprovider/wunderbyte:viewusage` (managers by default)
  through the web service `aiprovider_wunderbyte_get_usage`.
* When the usage cannot be read, the bar says so and the form keeps working.

## Data and privacy

**Sent to the AI service** with every request: the prompt (or the text to embed), for
`inspect_image` the images, for `generate_image` the size and quality asked for, the model name, a hash of the site identifier and the user id, the URL
of the Moodle site and the name of the action. The site URL lets the operator of the service see
which sites use a key. Names and email addresses are not added by this plugin; whatever the prompt
contains is sent as it is.

**Stored on the Moodle site**: one row per call with the request and the answer, in the tables
`aiprovider_wunderbyte_planner_decide` (also for `planner_construct`),
`aiprovider_wunderbyte_generate_agent_reply`, `aiprovider_wunderbyte_generate_content`,
`aiprovider_wunderbyte_inspect_image` (question and answer, not the images) and
`aiprovider_wunderbyte_generate_embeddings` (text and vector). Moodle's AI subsystem records which user sent a
request in `ai_action_register`. The API key is not stored in these tables. The calls of Moodle's
own actions (`generate_text`, `summarise_text`, `explain_text`, `generate_image`) are stored by the
AI subsystem in its own tables, and a generated image as a file of the user.

**Privacy API**: the plugin declares both, and it exports and deletes the stored requests and
answers of a user (`classes/privacy/provider.php`). A deletion empties the request and the answer
of a row and keeps the row with its token counts, as Moodle does for its own AI action tables.

## File layout

```
classes/
  provider.php               the provider: list of actions, authentication, usage of the key
  abstract_processor.php     shared sending, authentication and error handling
  process_chat_action.php    chat request and its answer
  process_*.php              one processor per action
  aiactions/                 the actions of this provider and their responses
  form/                      settings forms of the actions
  local/usage.php            the usage of a key as one value object
  local/action_tables.php    which action stores its calls in which table
  local/*_seeder.php         upgrade helpers that add a new action to existing instances
  external/get_usage.php     web service of the AI credit bar
  hook_listener.php          adds the API key field and the AI credit bar to the provider form
  privacy/provider.php       Privacy API
amd/src/usage_bar.js         the AI credit bar
templates/                   usage_bar and usage_bar_compact
db/                          tables, capability, web service, hook, upgrade, uninstall
lang/en/                     language strings
tests/                       PHPUnit tests
```
