# Wunderbyte AI provider (`aiprovider_wunderbyte`)

A Moodle **AI subsystem provider** (`core_ai`) that connects Moodle to an
OpenAI‑compatible inference gateway — in practice the Wunderbyte
[LiteLLM](https://github.com/BerriAI/litellm) proxy (e.g. `https://llm.wunderbyte.at`).

Alongside Moodle's standard text generation, it adds the three actions the
Wunderbyte booking agent needs — **planning**, **embeddings** and **final reply
composition** — and ships a built‑in, capability‑gated **AI‑credit bar** so
administrators always see what a key has spent and how much budget is left.

> Plugin location: `ai/provider/wunderbyte/` · Component: `aiprovider_wunderbyte`
> Maturity: BETA · Licence: GNU GPL v3+ · Requires Moodle 5.x (`2025092600`)

---

## Why it exists

Moodle core ships an OpenAI provider, but the booking agent requires
provider‑specific actions (planner routing, embeddings, agent reply) and
**cost transparency** that core does not offer. This plugin extends
`\core_ai\provider` to deliver both, while staying fully inside the supported
`core_ai` extension points — no core patches.

## What it does

| Action | Type | Purpose |
|---|---|---|
| `generate_text` *(core)* | chat | Generic instruction‑following text generation. |
| `planner_decide` | chat → JSON | Picks the best task candidate and returns a structured routing decision. |
| `generate_agent_reply` | chat | Composes the final, user‑facing answer in the requested language. |
| `generate_embeddings` | embeddings | Produces vector embeddings for the agent's task catalogue and user queries. |

All four are standard `core_ai` actions and are individually configurable and
toggleable in the Moodle AI placement settings.

## How a request flows

```
core_ai action  ──►  process_*  ──►  abstract_processor
(planner_decide,      (chat or        │  • builds the OpenAI-style payload
 agent_reply,          embeddings)    │  • adds "Authorization: Bearer <apikey>"
 generate_text,                       │  • sends via Moodle core\http_client
 embeddings)                          │  • maps errors through core_ai\error\factory
                                      ▼
                         OpenAI-compatible endpoint
                         (LiteLLM proxy → upstream model)
```

* **`abstract_processor`** centralises auth, transport and error handling.
* **`process_chat_action`** builds a `/chat/completions` request
  (`messages: [system, user]`, plus any per‑action model settings) and returns
  `{ id, generatedcontent, finishreason, prompttokens, completiontokens, model }`.
* **`process_generate_embeddings`** calls the embeddings endpoint and returns the
  vector plus its dimension count.
* The endpoint, model and system instruction are **per action**, so each action
  can target a different model on the same gateway.

## Configuration

Configured under **Site administration → General → AI → Manage AI providers**.

| Setting | Where | Notes |
|---|---|---|
| **API key** | Provider form | Sent only as a `Bearer` token to the configured endpoint. Stored as masked provider config; required. |
| **Endpoint** | Per action | Full URL of the chat/embeddings endpoint, e.g. `https://llm.wunderbyte.at/v1/chat/completions`. |
| **Model** | Per action | Model name as known to the gateway. |
| **System instruction** | Per action (chat) | Sensible defaults are provided per action. |
| **Dimensions** | Embeddings action | Optional embedding vector size. |

The provider reports itself as configured as soon as an API key is present.

## AI‑credit transparency

A first‑class feature for operators who need to keep spend visible:

* **`provider::get_key_usage()`** performs a read‑only management call
  (`GET /key/info`) against the LiteLLM proxy, **authenticating with the
  instance's own virtual key** — no master key is ever needed on the Moodle side.
* It is **not** an AI action: it consumes **no tokens**, produces no content and
  is deliberately kept out of the `core_ai` action pipeline.
* Results are normalised into the immutable `local\usage` value object — the
  single data contract shared by the web service, the renderable and every UI
  placement:

  | Field | Meaning |
  |---|---|
  | `spend`, `maxbudget`, `remaining`, `percentused` | Current budget window (EUR). |
  | `unlimited` | Key has no cap. |
  | `budgetduration`, `resetat`, `expiresat` | When spend resets / the key expires. |
  | `available`, `error`, `detail` | Read status; `detail` is **guaranteed secret‑free**. |

* The **AI‑credit bar** is injected into the provider form via a `core_ai` form
  hook and rendered client‑side (`amd/src/usage_bar.js` + Mustache templates),
  fed by the AJAX web service `aiprovider_wunderbyte_get_usage`.
* Visibility is gated by the capability **`aiprovider/wunderbyte:viewusage`**
  (granted to *managers*; site admins bypass). Spend is treated as sensitive,
  organisation‑level data.

Any lookup failure degrades gracefully to an "unavailable" state with a
non‑secret diagnostic — the bar never blocks the settings form.

## Data & privacy

* Each request/response is logged to its own table for accounting and auditing —
  `ai_action_planner_decide`, `ai_action_generate_agent_reply`,
  `ai_action_generate_embeddings` — capturing the prompt, generated content,
  finish reason and token counts. No API keys are stored in these tables.
* **Site identification:** every request carries the URL of the Moodle site
  (`$CFG->wwwroot`) as request metadata (`metadata.moodle_site`). Wunderbyte uses
  this exclusively to attribute the API key to the sites actually using it (key
  management and support — e.g. showing which site a key belongs to). No
  additional calls are made for this; the value rides on the requests the plugin
  sends anyway, and no personal data is part of it.
* A `privacy` provider is implemented for GDPR/privacy API compliance; the site
  URL transmission is declared there as well.
* Outbound HTTP uses Moodle's `core\http_client`, so site proxy and TLS
  settings apply.

## Security at a glance

* API key lives in Moodle provider config and is transmitted **only** as a
  `Bearer` header to the configured endpoint.
* Usage introspection is **read‑only, token‑free, master‑key‑free**, and never
  logs or returns secrets.
* Cost visibility is **capability‑restricted**.
* OpenAI‑compatible by design → works with any compliant, self‑hostable gateway
  (LiteLLM); budgets are denominated in **EUR**.

## File layout

```
classes/
  provider.php              # core_ai provider: action list, auth, usage lookup
  abstract_processor.php    # shared transport / auth / error handling
  process_chat_action.php   # chat-completions request + response mapping
  process_generate_*.php    # per-action processors (text, embeddings, reply, image…)
  aiactions/                # planner_decide, generate_agent_reply, generate_embeddings
  local/usage.php           # normalised usage value object (the data contract)
  external/get_usage.php    # AJAX web service for the credit bar
  hook_listener.php         # injects API-key field + usage bar into the provider form
  aimodel/                  # model metadata (gpt4o, o1, dalle3, …)
amd/src/                    # usage_bar.js, modelchooser.js
templates/                  # usage_bar(.compact).mustache
db/                         # services, access (capability), hooks, install.xml
lang/en/                    # strings
tests/                      # PHPUnit coverage for processors, provider, usage
```

## Requirements

* Moodle 5.x (`$plugin->requires = 2025092600`).
* An OpenAI‑compatible endpoint and a valid API/virtual key. The AI‑credit bar
  additionally expects a LiteLLM‑style `/key/info` management endpoint at the
  gateway host root.
