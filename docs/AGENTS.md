# Working on ReactWoo Geo Core

**Default workflow:** use **one Cursor chat** (or your usual dev flow). Follow **`docs/geo-core-cursor-master-plan.md`** and the active phase note in **`docs/phases/`**. Implement in order; avoid spinning many parallel “agent” chats unless you explicitly want that overhead.

### For AI assistants (autonomy + full stack)

- **Do not** end every reply by asking the user to “continue” or “confirm next step.” If work is incomplete, **take the next shippable step** from the master plan / **§18** backlog (or obvious follow-ups: tests, docs, version bumps) until a natural stopping point or a true blocker.
- **All layers are in scope** unless the user explicitly narrows: Geo Core engine, REST, **wp-admin**, satellite plugin UIs, hooks, and readme/changelog. A “narrow” or “hooks-only” request is **optional** and temporary — it is **not** a general rule to avoid admin UI.
- If the user only wanted to stop *being prompted* to continue, **still advance the plan** in the same session without waiting for a new message.

| Doc | Role |
|-----|------|
| `docs/geo-core-cursor-master-plan.md` | Product/architecture source of truth |
| `docs/releases-and-git-tags.md` | Version bumps, **git annotated tags**, push, staging/R2; license server **`packages`** / **`package_type`** for Geo satellite plugins |
| `docs/phases/phase-1.md` | Phase 1 (core refactor) — done |
| `docs/phases/phase-2.md` | Phase 2 (multi-country scaffolding) — done |
| `docs/phases/phase-3.md` | Phase 3 (free UX) — complete |
| `docs/phases/phase-4.md` | Phase 4 (Elementor + Geo Core bridge) — complete |
| `docs/phases/phase-5.md` | Phase 5 (AI layer) — Core bridge shipped; Geo AI plugin owns product UX |
| `docs/phases/phase-6.md` | Phase 6 (experiments / events) — Core hooks + REST; Geo Optimise owns assignment + stats |
| `docs/phases/phase-7.md` | Phase 7 (Geo Commerce) — WooCommerce pricing rules + geo merge in satellite plugin |

**Master plan execution:** Geo Core phases **1–7** are **complete for engine/contracts** (see **§16**). **§17** records shipped satellite depth; **§18** is the rolling backlog. REST **`GET …/v1/capabilities`** includes **`satellites`** (`geo_ai`, `geo_optimise`, `geo_commerce`: `ready`, `version`) and **`integration.satellite_actions`** / **`satellite_filters`** (hook names for discovery).

## Satellite plugins (reference versions)

| Folder | Version line (check `Version:` in main file) | Load hook | Notable APIs |
|--------|-----------------------------------------------|-----------|--------------|
| `reactwoo-geo-ai/` | 0.1.12+ | `rwga_loaded` | Dashboard **REST v1 base** + **REST capabilities** + **REST location**; **variant-draft REST smoke** (local validation POST); **`RWGA_Block_Editor`** (sidebar REST URL, copy + open tab + JS i18n), **`RWGA_Usage`**, `rwga_usage_display_rows`, `RWGA_Connection::get_summary()`, `rwga_stats_snapshot`; Core AI REST + `rwgc_ai_*` |
| `reactwoo-geo-optimise/` | 0.1.12+ | `rwgo_loaded` | **`assignment_per_route_resolved`**; **`csv_export_count`** / **`last_csv_export_gmt`**; **`rwgo_export_csv_filename`**, **`rwgo_get_variant`**, **`experiment_variant_counts`**, **`RWGO_Stats::flatten_for_csv`**, `rwgo_get_assignment_map()`, Core assignment events, `rwgo_stats_snapshot`, CSV export |
| `reactwoo-geo-commerce/` | 0.2.15+ | `rwgcm_loaded` | **`RWGCM_Admin_Orders_List`** (visitor country column, **sortable**); **`RWGCM_Catalog_Price_Variable`**; dashboard **capabilities JSON**; **Fee rules** + **`tax_class`**; **`rwgcm_fee_rule_rows`**, **`rwgcm_skip_pricing_for_cart_item`**; **`rwgcm_package_rates`**; coupons; UTM; **`rwgcm_cart_fees`**, **`rwgcm_checkout_order_meta`**, `rwgcm_geo_data`, `rwgcm-pricing` |

Each declares **`Requires Plugins: reactwoo-geocore`** and boots on **`plugins_loaded` priority 20** after Geo Core.

## Product rule (plans + implementation)

**No CSV as primary input:** Admin, blocks, and Elementor controls must use **prepopulated selectable** country/group controls — not comma-separated text fields. See master plan §5.1. Geo Commerce pricing uses WooCommerce country and **product category** multiselects.

**WordPress.org Geo Core:** Do not gate core geo (detection, routing, shortcodes, block, REST location) on a ReactWoo product license. Optional ReactWoo credentials are for AI/API add-ons only. See master plan §4 rule 12 and §8.

**Independent plugins:** **Geo AI**, **Geo Commerce (Woo)**, and **Geo Optimise** are **separate products** (own repos, releases). Do not grow Geo Core into a mega-plugin; keep integration **thin** (hooks, REST, filters). Full product UIs belong in those plugins.

**Linked to Core:** Those plugins are **not** standalone geo engines — they **require Geo Core** for visitor geo (APIs, hooks, routing). Do not duplicate MaxMind/detection stacks in satellite plugins. See master plan §1, §4.13–14, §8, §10–11.

## Quick orientation

- **Engine:** `includes/engine/`, `includes/rules/`, `includes/migration/`
- **Routing API:** `RWGC_Routing::get_route_decision_for_page()`, filters `rwgc_page_route_bundle`, `rwgc_route_variant_decision`
- **Geo data:** `RWGC_API::get_visitor_data()`, filter `rwgc_geo_data` (used for admin preview override)
- **Constants:** `RWGC_PLUGIN_SLUG`, `RWGC_TEXT_DOMAIN`, `RWGC_VERSION` — match REST `/capabilities` discovery.
- **REST discovery (when REST enabled):** `GET …/reactwoo-geocore/v1/capabilities` — `plugin_slug`, `text_domain`, `plugin_version`, `geo_ready`, `woocommerce_active`, **`satellites`**, `event_types`, `hooks`, `integration`; PHP: `rwgc_is_geo_core_active()`, `rwgc_get_rest_v1_url()` (filter `rwgc_rest_v1_url`), `rwgc_get_rest_location_url()`, `rwgc_get_rest_capabilities_url()`, `rwgc_get_geo_event_types()`, `rwgc_is_woocommerce_active()`. Geo Commerce: `docs/phases/phase-7.md`. Geo Optimise: `docs/phases/phase-6.md`.

## Optional: role-specific focus

If you want a **narrow prompt** for one area only, say so in chat (e.g. “engine only, no admin UI”). You do **not** need separate agent files or multiple prompts for normal work.
