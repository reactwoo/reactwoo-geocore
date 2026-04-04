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
| `reactwoo-geo-ai/` | 0.1.18+ | `rwga_loaded` | Top-level **wp-admin** menu (Overview, License, Drafts / Queue, Advanced, Help); Overview **Geo suite** quick links to other satellites; Geo Core dashboard **summary card**; **REST v1** + **capabilities** + **location**; **variant-draft REST smoke**; **`RWGA_Block_Editor`**, **`RWGA_Usage`**, `rwga_usage_display_rows`, `RWGA_Connection::get_summary()`, `rwga_stats_snapshot`; Core AI REST + `rwgc_ai_*` |
| `reactwoo-geo-optimise/` | 0.2.0+ | `rwgo_loaded` | Top-level **wp-admin** (Dashboard, Create Test, Tests, Reports, Help, **Tools** tabbed: Measurement / Developer / Diagnostics / Support, License); Geo Core dashboard **summary card**; **`assignment_per_route_resolved`**; **`csv_export_count`** / **`last_csv_export_gmt`**; **`rwgo_export_csv_filename`**, **`rwgo_get_variant`**, **`experiment_variant_counts`**, **`RWGO_Stats::flatten_for_csv`**, `rwgo_get_assignment_map()`, Core assignment events, `rwgo_stats_snapshot`, CSV export, **`rwgo_experiment`** CPT + goal events table |
| `reactwoo-geo-commerce/` | 0.2.20.0+ | `rwgcm_loaded` | Top-level **wp-admin** menu; Geo Core dashboard **summary card**; **`RWGCM_Admin_Orders_List`** (visitor country column, **sortable**); **`RWGCM_Catalog_Price_Variable`**; **Fee rules** + **`tax_class`**; **`rwgcm_fee_rule_rows`**, **`rwgcm_skip_pricing_for_cart_item`**; **`rwgcm_package_rates`**; coupons; UTM; **`rwgcm_cart_fees`**, **`rwgcm_checkout_order_meta`**, `rwgcm_geo_data` |

Each declares **`Requires Plugins: reactwoo-geocore`** (same slug as Core’s `wp-content/plugins/reactwoo-geocore/` folder and `RWGC_PLUGIN_SLUG`) and boots on **`plugins_loaded` priority 20** after Geo Core. **Geo Elementor** also declares **`Requires Plugins: elementor, reactwoo-geocore`**. Satellite **`package.json`** includes **`reactwooBuild.geoCoreDependencySlug`: `"reactwoo-geocore"`** for build parity checks.

**Cross-satellite admin:** Geo Elementor enqueues **`rwgc-admin`** + **`rwgc-suite`** + **`egp-geo-suite.css`** on its admin screens and shows **Geo suite** quick links (full card on Dashboard, compact **Suite links** on Rules). Geo AI Overview includes a **Geo suite** row linking to Core, Elementor (if active), Commerce, and Optimise when installed.

## Product rule (plans + implementation)

**No CSV as primary input:** Admin, blocks, and Elementor controls must use **prepopulated selectable** country/group controls — not comma-separated text fields. See master plan §5.1. Geo Commerce pricing uses WooCommerce country and **product category** multiselects.

**WordPress.org Geo Core:** Do not gate core geo (detection, routing, shortcodes, block, REST location) on a ReactWoo product license. Optional ReactWoo credentials are for AI/API add-ons only. See master plan §4 rule 12 and §8.

**Updates:** Geo Core is **free** (no product license). It registers **`RWGC_Satellite_Updater`** with slug **`reactwoo-geocore`** and **`attach_bearer_token` false** (no `Authorization` header on the HTTP client). **License enforcement is on the API** via **`slug`** + **`UPDATES_FREE_SLUGS`**; a local PHP flag cannot bypass JWT for paid slugs. **Commercial** satellites use **`attach_bearer_token` true** (default). If you later publish Core on WordPress.org, you can stop publishing **`reactwoo-geocore`** and remove the free slug / Core registration as needed.

**Independent plugins:** **Geo AI**, **Geo Commerce (Woo)**, and **Geo Optimise** are **separate products** (own repos, releases). Do not grow Geo Core into a mega-plugin; keep integration **thin** (hooks, REST, filters). Full product UIs belong in those plugins.

**Linked to Core:** Those plugins are **not** standalone geo engines — they **require Geo Core** for visitor geo (APIs, hooks, routing). Do not duplicate MaxMind/detection stacks in satellite plugins. See master plan §1, §4.13–14, §8, §10–11.

### City vs country (ReactWoo product split)

- **Country** is the baseline contract everywhere: Geo Core visitor APIs, **free page variant routing** (`RWGC_Routing` — see `includes/class-rwgc-routing.php`), Geo Commerce, Geo Optimise hooks, and Geo Core’s Elementor **document** geo visibility use **country** for decisions.
- **City** (and city-based **matching / routing** in Elementor) is a **Geo Elementor** concern: the City Targeting add-on and related Elementor rules. In deployments where the **city database lives on the ReactWoo API** (not only a local MaxMind City DB), Core may still populate `city` / `region` on the visitor payload for **display, shortcodes, REST, and debugging**, but **do not** implement city-based page routing inside Geo Core — that stays in Geo Elementor’s layer.
- When extending agents or APIs: treat `rwgc_get_visitor_city()` / `rwgc_get_visitor_data()['city']` as **informational** for Core; **city rule evaluation** belongs in **geo-elementor** (and its API bridge), not in `RWGC_Routing`.

## Geo Suite admin shell (Phase 1+)

- **`RWGC_Admin_UI`** (`includes/class-rwgc-admin-ui.php`): reusable stat cards, checklist rows, badges, quick-action row, satellite card grid; filter **`rwgc_suite_satellite_definitions`**.
- **`admin/css/rwgc-suite.css`**: suite tokens + component classes; enqueued after **`admin/css/admin.css`** on all `rwgc-*` screens.
- **Suite onboarding (MVP):** **`RWGC_Suite_Admin`**, **`RWGC_Module_Registry`**, **`RWGC_Onboarding`**, **`RWGC_Workflows`**, **`RWGC_Variant_Manager`** — *Suite Home*, 3-step *Getting Started*, *Create page version*, *Page versions* overview; first-activation redirect to Getting Started; hooks in **`docs/GEO_SUITE_HOOKS.md`**.
- **`admin/css/suite-admin.css`**: Suite Home / wizard layouts (enqueued on suite screens only).
- **Branding:** `assets/icon-128x128.png`, `assets/icon-256x256.png` (WordPress/plugin UI expectations).

## Quick orientation

- **Engine:** `includes/engine/`, `includes/rules/`, `includes/migration/`
- **Routing API:** `RWGC_Routing::get_route_decision_for_page()`, filters `rwgc_page_route_bundle`, `rwgc_route_variant_decision`
- **Geo data:** `RWGC_API::get_visitor_data()`, filter `rwgc_geo_data` (used for admin preview override)
- **Constants:** `RWGC_PLUGIN_SLUG`, `RWGC_TEXT_DOMAIN`, `RWGC_VERSION` — match REST `/capabilities` discovery. **Release zip root + artifact name:** `package.json` → **`reactwooBuild.pluginFolder`** / **`reactwooBuild.zipFile`** (see `docs/releases-and-git-tags.md`); `npm run package:zip`.
- **REST discovery (when REST enabled):** `GET …/reactwoo-geocore/v1/capabilities` — `plugin_slug`, `text_domain`, `plugin_version`, `geo_ready`, `woocommerce_active`, **`satellites`**, `event_types`, `hooks`, `integration`; PHP: `rwgc_is_geo_core_active()`, `rwgc_get_rest_v1_url()` (filter `rwgc_rest_v1_url`), `rwgc_get_rest_location_url()`, `rwgc_get_rest_capabilities_url()`, `rwgc_get_geo_event_types()`, `rwgc_is_woocommerce_active()`. Geo Commerce: `docs/phases/phase-7.md`. Geo Optimise: `docs/phases/phase-6.md`.

## Optional: role-specific focus

If you want a **narrow prompt** for one area only, say so in chat (e.g. “engine only, no admin UI”). You do **not** need separate agent files or multiple prompts for normal work.
