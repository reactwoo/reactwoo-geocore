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

## Site intelligence snapshot (Geo AI cloud sync)

Shipped alongside the variant-draft bridge:

- **`rwgc_build_ai_snapshot()`**, **`rwgc_get_ai_snapshot_hash()`** — compact geo configuration metadata (no page content / Elementor JSON / PII).
- **`RWGC_AI_Snapshot_Builder`**, schema v1, admin preview, sync status.
- Filter **`rwgc_ai_snapshot_payload`** for satellite rows.
- Full contract: **`docs/GEO-AI-SNAPSHOT.md`**. Cross-repo intelligence plan: **`reactwoo-api/docs/PLAN-GEO-AI-INTELLIGENCE.md`**.

## Next

- **Geo AI plugin** (`reactwoo-geo-ai/` v0.4.66+): block editor sidebar, remote workflows, **site intelligence sync**, **approval-gated intelligence actions** — see **`reactwoo-geo-ai/docs/GEO-AI-INTELLIGENCE.md`**.
- Core **thin** AI surface remains; see **`docs/phases/phase-6.md`** for experiments/events. Master plan **§17–§18**.
