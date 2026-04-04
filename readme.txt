=== ReactWoo Geo Core ===
Contributors: reactwoo
Tags: geo, geolocation, maxmind, country, currency
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shared geolocation engine for ReactWoo plugins and WordPress sites. Provides MaxMind-based country detection, cache, shortcodes, REST API, and a Gutenberg block.

== Description ==

ReactWoo Geo Core is a free geolocation engine for WordPress.

It provides:

* MaxMind-based country detection (GeoLite2 Country)
* Centralised storage and update of the MaxMind database
* A simple PHP API for add-ons and themes
* Shortcodes for country, city, and currency
* A REST API endpoint for frontend apps
* A basic Gutenberg "Geo Content" block
* Free page-level master/secondary routing (server-side, 1 master + 1 secondary country mapping per master page)

It is designed to be used on its own, or as a shared geo engine for premium ReactWoo plugins such as GeoElementor and ReactWoo WHMCS Bridge.

== Installation ==

**No Composer, SSH, or WP-CLI is required on your server.** Geo Core ships with bundled PHP libraries under `vendor/` (MaxMind / GeoIP2). You only upload or update the plugin like any other WordPress plugin.

1. Upload the plugin files to the `/wp-content/plugins/reactwoo-geocore` directory, or install via WordPress plugin upload.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Geo Core → Settings** and enter your **MaxMind** (GeoLite2) account credentials. This is a third-party MaxMind license for downloading the database — not a ReactWoo product license. Core geo works without any ReactWoo key.
4. Use the **Tools** tab to download/update the database and test lookups.

== Usage ==

After setup, you can use Geo Core in multiple ways:

* **Shortcodes**: `[rwgc_country]`, `[rwgc_country_code]`, `[rwgc_currency]`, `[rwgc_city]`, `[rwgc_region]`
* **Conditional shortcode**: `[rwgc_if country="US,CA"]Special content[/rwgc_if]`
* **PHP helpers**: `rwgc_get_visitor_country()`, `rwgc_get_visitor_currency()`, `rwgc_get_visitor_data()`
* **REST endpoints** (when enabled in settings): `/wp-json/reactwoo-geocore/v1/location` (visitor geo), `/wp-json/reactwoo-geocore/v1/capabilities` (plugin discovery: event types and hooks; no visitor PII)
* **Gutenberg**: Use the **Geo Content** block to show/hide content by country
* **Elementor (free baseline)**: Use Page/Popup document settings for basic show/hide by country
* **Page Variant Routing (free)**: Edit any page and use "Geo Variant Routing (Free)" to set page role (Master/Secondary) with server-side redirect mapping (1 secondary country mapping per master)

For an in-dashboard guide, open **Geo Core → Usage** in wp-admin.

Country targeting uses ISO2 **country codes** (example: `US`, `CA`, `GB`) because they are stable and reliable for logic. Use country **names** for display text to visitors.

Example conditional content:

`[rwgc_if country="US,CA"]Free shipping for North America[/rwgc_if]`

== Frequently Asked Questions ==

= Does this plugin require Elementor? =

No. ReactWoo Geo Core works with any theme and editor. It exposes helper functions, shortcodes, a REST endpoint, and a Gutenberg block.

= Does this plugin include MaxMind? =

No. You must provide your own MaxMind license key and accept their terms of use. The plugin then downloads the GeoLite2 Country database to your site.

= Does Geo Core require a ReactWoo product license? =

No. Detection, shortcodes, the Gutenberg block, page routing, and the public REST location endpoint work without a ReactWoo key. A ReactWoo product license in settings is **optional** and used only if you enable optional AI-assisted features that call the ReactWoo API.

= Does the server need Composer or SSH? =

**No.** All required PHP packages are included in the plugin’s `vendor/` directory. You should never need to run `composer install` on a customer site. (Composer is used only when **we** build the release zip in development or CI.)

= Does this plugin require WooCommerce? =

No. Geo Core runs without WooCommerce. The optional **Geo Commerce** product (separate plugin) adds Woo-specific overlays and uses `rwgc_is_woocommerce_active()` / the REST `woocommerce_active` field for discovery.

== Changelog ==

= 1.3.9 =
* **Docs:** Installation and FAQ state clearly that **no Composer or SSH** is required on customer hosting; libraries ship in `vendor/`.
* **Release tooling:** `scripts/package_zip.py` fails the build if production `vendor/composer/autoload_static.php` still references dev-only packages (prevents broken zips).

= 1.3.8 =
* **Bundled vendor:** Fixed a bad release build where Composer autoload pointed at **dev-only** test libraries that were not included in the zip — **no customer action or hosting tools required**; sites only install the update as usual. Release zips are now built with production dependencies only; CI runs that step before packaging.

= 1.3.7 =
* **License login:** Filter **`rwgc_auth_login_body`** — satellites can add fields (e.g. `product_slug`, `catalog_slug`) to the JSON body for `POST /api/v5/auth/login` before the JWT is minted.

= 1.3.6 =
* **Geo Core updates (R2, no product license):** Geo Core registers `RWGC_Satellite_Updater` with catalog slug `reactwoo-geocore` and `attach_bearer_token` false (no `Authorization` header; enforcement is server-side by slug). The API skips JWT only for slugs in `UPDATES_FREE_SLUGS` (default includes `reactwoo-geocore`) when `UPDATES_REQUIRE_LICENSE_TOKEN` is on. Publish zips with the same `POST /api/v5/updates/publish` flow as commercial plugins. Docs: `docs/GEO_SUITE_HOOKS.md`, `docs/AGENTS.md`.

= 1.3.5 =
* **Satellite updates:** `RWGC_Satellite_Updater` only calls the updates API for commercial slugs when a valid license JWT is available (same login as `RWGC_Platform_Client`); no unauthenticated check for paid products.

= 1.3.4 =
* **Satellite updates:** `RWGC_Satellite_Updater` — commercial satellites can register plugin updates via `POST /api/v5/updates/check` using the same JWT as `RWGC_Platform_Client` (R2 signed `download_url`). Filter: `rwgc_satellite_updater_items`.

= 1.3.3 =
* **Suite handoff:** Public helper `rwgc_get_suite_handoff_request_context()` for satellite admin UIs; filter `rwgc_suite_handoff_request_context`. Documented in `docs/GEO_SUITE_HOOKS.md`.

= 1.3.2 =
* **Getting Started:** True 3-step flow (goal → environment → admin detection preview) with visual stepper; wizard actions `goal`, `advance_env`, `complete`; legacy onboarding states normalize to the correct step.
* **Page versions:** New **Geo Core → Page versions** screen listing default pages with routing and their local version (if any); links to pre-fill **Create page version** with `rwgc_master_page_id`.
* **Workflows:** Launcher filter runs before handoff query args; next-step links to Geo AI / Geo Optimise include `rwgc_variant_page_id` when a variant was just created.
* **Filter:** `rwgc_routing_overview_rows` for the Page versions table.

= 1.3.1 =
* **Suite UX:** Goal-based guidance panel on Getting Started, launchers reordered to match the saved goal, Suite Home readiness uses the same goal for WooCommerce-focused messaging.
* **Workflows:** Default `rwgc_next_steps` for successful variant creation (filterable); success screen uses that list instead of hard-coded buttons.
* **Activity:** `rwgc_suite_activity_providers` — callables can append rows; list is merged, sorted by time, then passed through `rwgc_suite_activity`.

= 1.3.0 =
* **Geo Suite shell (MVP):** Suite Home and Getting Started screens, environment readiness table, task-first workflow launchers, guided “Create country page version” flow (uses the same routing rules as the page meta box), recent activity log, and first-activation redirect to Getting Started.
* **Hooks:** `rwgc_register_modules`, `rwgc_workflow_launchers`, `rwgc_suite_activity`, `rwgc_variant_created`, etc. — see `docs/GEO_SUITE_HOOKS.md`.

= 1.2.5 =
* **Dashboard:** Compact add-on cards (`.rwgc-addon-card`), metadata pills, visitor stats grid, subdued technical reference panel; satellite grid uses the same card styles.
* **Satellites:** Geo AI, Geo Commerce, and Geo Optimise Geo Core dashboard summaries use the shared card markup and shorter copy.

= 1.2.4 =
* **Release:** Patch bump for remote update pipeline (version-only).

= 1.2.3 =
* **Geo Suite UX (Phase 1):** Shared **`RWGC_Admin_UI`** helpers (stat cards, checklist, badges, satellite grid) + **`admin/css/rwgc-suite.css`** design tokens. Dashboard rework: welcome hero, status grid, setup checklist, quick actions, satellite cards; technical matrix/shortcodes moved under **Technical reference** `<details>`.
* **Assets:** `assets/icon-128x128.png`, `assets/icon-256x256.png` for updater/branding (initial ReactWoo Geo Core artwork).

= 1.2.2 =
* **Dashboard:** Action **`rwgc_dashboard_satellite_panels`** — satellite plugins (Geo Commerce, Geo AI, Geo Optimise) can add summary cards on the Geo Core dashboard.
* **Docs:** **`docs/AGENTS.md`** — satellite version lines and top-level admin menu notes.

= 1.2.1 =
* **Version:** Aligns distributed build with **1.2.1** (`RWGC_VERSION`, plugin header).
* **Documentation:** City vs country product split; canonical **`reactwoo-geocore`** slug table in **`docs/releases-and-git-tags.md`**; **`package.json`** **`reactwooBuild`** (`pluginFolder`, `zipFile`, `pluginSlug`).
* **Elementor (free):** Visitor location preview block on document settings (country, city, region, IP) when Core is ready.
* **Routing:** Docblocks clarify **`RWGC_Routing`** is country-only; city rules remain in Geo Elementor.

= 0.1.10.1 =
* **Partner plugins:** Geo Elementor **1.0.5.28+** uses the correct Geo Core Settings screen slug (`rwgc-settings`). Routing metadata remains in Core; **`RWGC_Legacy_Route_Mapper`** and **`RWGC_Migration`** handle legacy data inside Geo Core — extensions should call **`RWGC_Routing`** / REST discovery only.

= 0.1.10.0 =
* **Admin:** Filter **`rwgc_inner_nav_items`** — satellite plugins (Geo AI, Geo Optimise, Geo Commerce) can add links to the shared **Geo Core** horizontal section nav (same UX pattern as Geo Elementor inner nav).
* **Core:** Routing engine (context, page route resolver, variants, fallback), geo events, rule condition evaluator, legacy route migration helpers.
* **AI bridge:** **`RWGC_AI_Orchestrator`**, platform client and preview helpers where applicable.
* **Developer experience:** `docs/` (AGENTS, phases, QA, releases-and-git-tags), PHPUnit config and engine unit tests; `.gitignore` extended for local tooling.
* **Dependencies:** Composer lock and vendor autoload maps refreshed (production packages).

= 0.1.9.0 =
* REST **`integration.satellite_filters`:** **`rwgo_export_csv_filename`** (Geo Optimise CSV export).

= 0.1.8.0 =
* REST: **`rwgcm_fee_rule_rows`**, **`rwgcm_skip_pricing_for_cart_item`** (Geo Commerce fees + bundle-safe pricing).

= 0.1.7.0 =
* REST: **`rwgcm_coupon_allowed_for_visitor`**, **`rwgcm_coupon_valid_when_country_unknown`** (Geo Commerce coupon geo).

= 0.1.6.0 =
* REST: **`rwgcm_package_rates`** (Geo Commerce shipping extension).

= 0.1.5.9 =
* REST: **`rwga_usage_display_rows`** (Geo AI cached usage table).

= 0.1.5.8 =
* REST: **`rwgcm_store_utm_on_orders`**, **`rwgcm_attribution_query_keys`** (Geo Commerce attribution).

= 0.1.5.7 =
* REST: **`rwgcm_cart_fees`**, **`rwgcm_checkout_order_meta`**.

= 0.1.5.6 =
* REST `/capabilities`: `rwgcm_order_attributed`, `rwgcm_order_visitor_geo`, `rwgo_emit_assignment_geo_event`.

= 0.1.5.5 =
* REST `/capabilities` → `satellite_filters`: **`rwgcm_apply_catalog_price`**.

= 0.1.5.4 =
* REST `/capabilities` → `satellite_filters`: **`rwga_stats_snapshot`**.

= 0.1.5.2 =
* REST `/capabilities` → `integration`: `rwgo_variant_assigned`, `rwgcm_adjusted_unit_price`.

= 0.1.5.1 =
* REST `/capabilities` → `integration`: document `rwgcm_before_cart_totals` and `rwgo_stats_snapshot`.

= 0.1.5.0 =
* REST **`GET …/capabilities`**: `satellites` object (`geo_ai`, `geo_optimise`, `geo_commerce`) with `ready` + `version` when each load hook ran. `integration` lists `satellite_actions` / `satellite_filters`. Master plan **§17** (next wave backlog).

= 0.1.4.0 =
* Master plan **§16** (execution status). Docs + AGENTS: Core phase contracts complete; satellites (`reactwoo-geo-ai`, `reactwoo-geo-optimise`, `reactwoo-geo-commerce`) carry product depth.

= 0.1.3.8 =
* Docs: satellite plugin scaffolds (`reactwoo-geo-ai`, `reactwoo-geo-commerce`, `reactwoo-geo-optimise`) live alongside Geo Core in `wp-content/plugins/`; see `docs/AGENTS.md` and master plan §1.

= 0.1.3.7 =
* Constants `RWGC_PLUGIN_SLUG`, `RWGC_TEXT_DOMAIN`; helper `rwgc_is_geo_core_active()` for satellite guards. Phase 6 doc: Geo Optimise checklist. Phase 7 checklist uses the helper.

= 0.1.3.6 =
* REST `/capabilities`: `plugin_slug` and `text_domain` for satellite discovery. `docs/phases/phase-7.md` — Geo Commerce author checklist.

= 0.1.3.5 =
* Filter `rwgc_rest_v1_url` for REST URL overrides; listed under `/capabilities` → `integration.filters`. Phase 5 doc: Core AI bridge marked shipped.

= 0.1.3.4 =
* `rwgc_get_rest_v1_url()`, `rwgc_get_rest_location_url()`; refactored capabilities URL helper. Master plan §10: Geo Core phase status paragraph.

= 0.1.3.3 =
* Phase 7 prep: `rwgc_is_woocommerce_active()` (filter `rwgc_is_woocommerce_active`); REST `/capabilities` includes `woocommerce_active` for Geo Commerce discovery.

= 0.1.3.2 =
* REST `/capabilities` includes `integration` (curated filter, action, and AI filter names) for satellite plugins.

= 0.1.3.1 =
* Phase 6: `rwgc_get_rest_capabilities_url()` helper; Usage screen lists `/location` and `/capabilities` when REST is enabled.

= 0.1.3.0 =
* Phase 6: REST GET `/capabilities` (discovery: version, geo_ready, event_types, hooks; no PII). `RWGC_Event::known_event_types()` and `rwgc_get_geo_event_types()` with filter `rwgc_geo_event_known_types`.

= 0.1.2.9 =
* Phase 6 (events): `route_redirect` geo event before server-side variant redirect; filter `rwgc_emit_route_redirect_event`. Docs: `docs/phases/phase-6.md`, `docs/phases/phase-7.md`.

= 0.1.2.8 =
* Phase 5 (AI): Tools page — optional ReactWoo AI reachability test (no license) and authenticated assistant usage test; phase doc updated.

= 0.1.2.7 =
* Dashboard and Usage guide: MaxMind (GeoLite2) vs optional ReactWoo product license; REST location described as license-free for core geo. Phase docs: `docs/phases/phase-4.md` complete, `docs/phases/phase-5.md` (AI) stub.

= 0.1.2.6 =
* Clarified WordPress.org positioning: core geo does not require a ReactWoo product license; MaxMind vs ReactWoo credentials distinguished in settings and docs.

= 0.1.2.2 =
* Expanded country list support in geo visibility controls.
* Added master/secondary free-routing flow improvements and Elementor-side routing controls.

= 0.1.2.1 =
* Added WordPress-safe inner section navigation tabs across Geo Core admin pages.
* Refined admin spacing and card rhythm for closer alignment with approved dashboard design.

= 0.1.2 =
* Added free page-level variant routing with server-side redirects (1 default + 1 country mapping per page).
* Added page editor controls and validation for routing mappings.
* Added extension filter contract for GeoElementor advanced routing integration.

= 0.1.1 =
* Added admin dashboard and usage guide improvements for onboarding.
* Added Elementor free baseline geo visibility for page/popup documents.
* Updated WordPress.org submission compliance metadata and license file.

= 0.1.0 =
* Initial beta release.

