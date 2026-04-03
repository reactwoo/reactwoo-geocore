# Phase 5 — AI layer (Core bridge: shipped)

Aligned with `docs/geo-core-cursor-master-plan.md` §10 Phase 5 and §4 rules 3, 12–**14**. Product iteration continues in the **Geo AI** plugin; Geo Core’s **thin** bridge is stable.

**Product boundary:** The full **Geo AI** experience (screens, jobs, review flows, billing UX) is a **separate WordPress plugin** developed independently. Scaffold: **`reactwoo-geo-ai/`** in `wp-content/plugins/` (hook `rwga_loaded`). **Geo Core** may hold only **thin** integration: REST bridge, orchestrator, Tools diagnostics, and filters — enough for the AI plugin and editors to call into the engine without Geo Core becoming the AI product.

## Goal

Optional **AI-assisted variant drafts** via **ReactWoo API** — drafts and review only; **no silent publish** of live content.

## Boundaries (non-negotiable)

- **WordPress.org Geo Core** remains free: **no ReactWoo product license** is required for detection, routing, shortcodes, block, or the public **`/location`** REST endpoint (see master plan §4.12, §8).
- **ReactWoo product license** in Geo Core settings is **optional** and used **only** to obtain an API token for AI/API calls (`RWGC_Platform_Client`, AI orchestration, `/ai/variant-draft`).
- **Output** is suggestion/draft data returned to editors; publishing stays explicit and user-controlled.

## Implementation (shipped so far)

- **REST:** `POST /wp-json/reactwoo-geocore/v1/ai/variant-draft` — `permissions_ai_draft` → `edit_pages`; delegates to `RWGC_AI_Orchestrator::request_variant_draft()` → `POST /api/v5/ai/geo/variant-draft` with Bearer token.
- **Orchestrator:** `RWGC_AI_Orchestrator` — `ai_health()` (unauthenticated reachability), `get_usage()` (authenticated assistant usage), `request_variant_draft()`; filters `rwgc_ai_variant_draft_payload`, `rwgc_ai_variant_draft_response`.
- **Tools (wp-admin):** **Geo Core → Tools** — optional “ReactWoo AI” card: **Test AI service reachability** (no license) and **Test license & API (assistant usage)** (requires ReactWoo product key in settings).

## Next

- **Geo AI plugin** (`reactwoo-geo-ai/` v0.1.11+): **block editor** page sidebar (variant-draft REST URL, copy + open tab + translations); dashboard **REST capabilities** + **REST location** links, **assistant token usage**, **`rwga_usage_display_rows`**, **`rwga_stats_snapshot`**. Block product depth can extend here.
- Core **thin** AI surface remains; see **`docs/phases/phase-6.md`** for experiments/events. Master plan **§17–§18**.
