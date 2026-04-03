# Phase 4 — Elementor integration (complete)

Aligned with `docs/geo-core-cursor-master-plan.md` §10 Phase 4.

## Goal

GeoElementor consumes Geo Core for detection and shared country lists; Pro routing extends Geo Core via hooks — **no duplicate country option sources** when both plugins run.

## Done (GeoElementor `1.0.5.27`)

- **`egp_merge_country_options_from_geo_core`** — Filter on `egp_country_options` (priority 5). When `RWGC_Countries` is available, Elementor admin, global settings, rules UI, and `egp_get_country_options()` use **Geo Core’s** `RWGC_Countries::get_options()` (WooCommerce-aware when WC is active).
- **`rwgc_route_variant_decision`** — `extend_rwgc_route_variant_decision()` updated for **four arguments** (`$decision`, `$config`, `$page_id`, `$context`) to match Geo Core; uses `$page_id` when `decision['page_id']` is empty.
- **Opt-in Pro routing extension** — `register_rwgc_extensions()` runs only when `egp_enable_rwgc_route_variant_extension` is true (default false). Sites that need Pro variant resolution on top of Geo Core baseline can add:
  `add_filter( 'egp_enable_rwgc_route_variant_extension', '__return_true' );`

## UI note (§5.1)

Elementor already uses **SELECT** / multi-select for countries in most flows; the list content is now aligned with Geo Core when both are active.

## Next

- Optional product decision: enable Pro route extension by default for licensed GeoElementor sites (outside Geo Core).
- **Phase 5** — AI layer: see `docs/phases/phase-5.md`.
