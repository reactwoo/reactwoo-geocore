# Phase 6 — Experiments & events (Core integration: advanced)

Aligned with `docs/geo-core-cursor-master-plan.md` §10 Phase 6.

**Product boundary:** Full **A/B assignment UI**, experiment admin, and reporting live primarily in **Geo Optimise** (and/or other satellite plugins). Scaffold: **`reactwoo-geo-optimise/`** (hook `rwgo_loaded`). **Geo Core** provides **vendor-neutral hooks, REST discovery, and event envelopes** so those plugins can subscribe without duplicating geo. Core-side Phase 6 work needed for satellite integration is largely in place; further iteration happens in **Geo Optimise** (and Phase 7 **Geo Commerce** for Woo).

## Goal

Stable **events** and extension points for impressions, assignments, and **route outcomes** — linked to `RWGC_Context` and route decisions.

## Boundaries

- **Geo Core** does not implement a full experimentation product; it emits structured payloads and actions.
- Satellite plugins **require Geo Core** for context (§4.14).

## Implementation (Geo Core)

- **`RWGC_Event`** — Envelope: `event_type`, `variant_id`, `experiment_id`, `assignment_id`, `context`, `subject`, `meta`. Constants include `TYPE_ROUTE_REDIRECT` for server-side variant redirects.
- **`RWGC_Events::emit()`** — `apply_filters( 'rwgc_geo_event', $payload )` then `do_action( 'rwgc_geo_event', $payload )`.
- **`rwgc_emit_geo_event( RWGC_Event $event )`** — Public helper in `functions-rwgc.php`.
- **`rwgc_route_variant_resolved`** — Fires when a route decision is computed (`get_route_decision_for_page`): `( $decision, $context, $config, $page_id )`.
- **`route_redirect` emission** — Before a **302** server-side redirect from free page routing, Core emits a geo event (`TYPE_ROUTE_REDIRECT`) with `meta.target_url`, `meta.http_status`. Filter: `rwgc_emit_route_redirect_event` (default true) to suppress.
- **`RWGC_Event::known_event_types()`** — Canonical `event_type` list; filter `rwgc_geo_event_known_types`. Helper **`rwgc_get_geo_event_types()`**.
- **REST URL helpers** — **`rwgc_get_rest_v1_url( $endpoint )`**, **`rwgc_get_rest_location_url()`**, **`rwgc_get_rest_capabilities_url()`** (empty when REST off). Filter **`rwgc_rest_v1_url`** on the built URL + endpoint.
- **REST `GET /wp-json/reactwoo-geocore/v1/capabilities`** — When REST is enabled: non-sensitive discovery (`plugin_slug`, `text_domain`, `plugin_version`, `geo_ready`, `woocommerce_active`, `event_types`, `hooks`, **`integration`**: curated `filters`, `actions`, `ai_filters` names for Geo Optimise / Commerce / AI). No visitor PII. Filter: `rwgc_rest_capabilities`.

## Checklist (Geo Optimise plugin authors)

1. Guard with **`rwgc_is_geo_core_active()`** (filter `rwgc_is_geo_core_active`) before registering experiments or listeners.
2. Use **`GET …/v1/capabilities`** to discover `event_types`, `integration`, and REST URLs (`rwgc_get_rest_v1_url`, etc.).
3. Subscribe to **`rwgc_geo_event`** and/or **`rwgc_route_variant_resolved`**; respect **`rwgc_emit_route_redirect_event`** if you wrap redirects.
4. Do **not** duplicate MaxMind or geo resolution — consume **`rwgc_get_visitor_data()`** / **`RWGC_Context`** patterns from Core.

## Next

- **Geo Optimise** (`reactwoo-geo-optimise/` v0.1.12+): **`assignment_per_route_resolved`**; **`csv_export_count`** / **`last_csv_export_gmt`** (snapshot + dashboard); CSV filename includes host; filter **`rwgo_export_csv_filename`**; **`experiment_variant_counts`**, export **`flatten_for_csv`**, dashboard table; **`rwgo_get_variant()`** weighted splits; Core **`assignment`** events via **`rwgo_emit_assignment_geo_event`**. Further funnel analytics: master plan **§18**.
- Phase 7 — Geo Commerce; see `docs/phases/phase-7.md`.
