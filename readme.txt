=== ReactWoo Geo Core ===
Contributors: reactwoo
Tags: geo, geolocation, maxmind, country, currency
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.8.60
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
3. Go to **Geo → Integrations → MaxMind (GeoLite2)** and enter your **MaxMind** account credentials. This is a third-party MaxMind license for downloading the database — not a ReactWoo product license. Core geo works without any ReactWoo key.
4. Download or upload the country database and test visitor detection on the same screen.

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

= 1.8.60 =
* **Platform UI:** Chat-style Targeting Assistant with conversation thread, live setup panel, shared button/badge system, simplified Insights satellite dashboard, inline Geo AI suggestions.

= 1.8.59 =
* **Admin IA:** Six-item Geo menu (Commerce hidden from sidebar); Targeting tabs Assistant / Variants / Rules / Advanced; country variants moved under Targeting; chat-style Targeting Assistant; central capability registry; Experiences locked state without Geo Optimise.

= 1.8.58 =
* **Insights:** Tabbed Capability Map refactor — compact health chips, short product cards, top opportunities preview, Setup & Readiness and AI Opportunities tabs, provider detail pages.

= 1.8.57 =
* **Site intelligence snapshot:** Exclude `generated_at_gmt` from snapshot hash (including nested blocks) so unchanged sites do not re-upload on every sync or cron tick.

= 1.8.55 =
* **WooCommerce GeoCore tab:** Bridge Geo Commerce weather fields into the GeoCore tab (supports current and legacy Commerce builds) and hide duplicate General-tab output.

= 1.8.54 =
* **WooCommerce GeoCore tab:** Fix product data tab layout (reset WC float styles), unique panel id, and first-click tab behaviour.
* **WooCommerce GeoCore tab:** Card sections for weather, geo targeting, catalogue boost, and storefront preview with extension hooks.

= 1.8.53 =
* **Rule builder:** Expose **Shopping weather** when GeoCore Pro BYOK weather is connected (Elementor, Geo Content block, Commerce portable rules) without requiring advanced targeting mode.
* **WooCommerce:** Product-level geo visibility tab and storefront filtering when WooCommerce is active.

= 1.8.52 =
* **Page Version URLs:** Treat `REQUEST_URI` as authoritative on base URLs (`/`, `/about`, etc.) so hide/show rules are not polluted by stale server rewrite vars.
* **LiteSpeed:** Vary full-page cache by page version (`rwgc_pv` cookie) so campaign `/_gc/{version}` HTML is not served on the default homepage URL.

= 1.8.50 =
* **Page Version URLs:** Remove global `remove_action( 'template_redirect', 'redirect_canonical' )`; only block canonical redirects that would strip `/_gc/{version}` from the inbound URL.

= 1.8.49 =
* **Page Version URLs:** Bootstrap canonical-redirect guards when the plugin file loads (before `plugins_loaded` priority 5) so `/_gc/{version}` is not 301-stripped on the static front page.

= 1.8.48 =
* **Page Version URLs:** Capture inbound URI early, cancel strip-to-home `wp_redirect`, and remove `redirect_canonical` on `/_gc/{version}` requests (fixes homepage variant targeting when WordPress still 301s to `/`).
* **LiteSpeed cookie:** Set `rwgc_cc` on `send_headers` with `headers_sent()` guard to avoid PHP warnings during redirects.

= 1.8.47 =
* **Page Version URLs:** Block WordPress `redirect_canonical` from 301-stripping homepage `/_gc/{version}` paths so variant targeting, popups, and hide rules can evaluate.

= 1.8.46 =
* **Plugin updates:** Settings → Advanced “Clear update cache & check now”; updater uses WordPress `update_plugins` checked version when calling api.reactwoo.com.

= 1.8.45 =
* **Elementor visibility:** Honor hide-when-rules-match from saved rule JSON, skip rendering hidden sections/containers via should_render, and sync visibility mode in the editor.
* **LiteSpeed:** Country vary cookie (`rwgc_cc`) so full-page cache can serve geo-targeted Elementor HTML per visitor.

= 1.8.44 =
* **Rule builder:** Page version name field no longer re-renders on every keystroke while typing.

= 1.8.43 =
* **Rule builder:** Static front page (Home) appears in the page-version picker and supports `/_gc/{version}` URLs.

= 1.8.42 =
* **Experience builder + Geo AI:** `ai_adapt` auto-generates copy drafts from duplicated page content plus visibility rule context (country, device, campaign, etc.). Public helpers `rwgc_get_visibility_rule_copy_context()`, `rwgc_get_page_experience_copy_context()`, `rwgc_get_country_codes_copy_context()`.

= 1.8.41 =
* **Experience builder:** Visitor conditions (everyone, multi-country, saved rule, create-rule handoff), four content modes (duplicate, existing, blank, Geo AI adapt), rule library picker, and visibility-rule return URL after save.
* **Suite handoff:** `rwgc_get_suite_handoff_request_context()` includes `master_page_id` and `geo_target` for Geo AI adapt flows.

= 1.8.40 =
* **Insights hub:** Site intelligence wizard card description for Geo AI guided setup.

= 1.8.39 =
* **Insights hub:** Card descriptions for Geo AI Cloud intelligence and Intelligence actions routes.

= 1.8.38 =
* **Geo AI site intelligence:** Compact site snapshot builder (`rwgc_build_ai_snapshot()`, schema v1) for Geo AI cloud sync — rules, variants, popups, relationships; no page content or PII. Admin preview under **Settings → AI Data Snapshot**. Filter `rwgc_ai_snapshot_payload` for satellite plugins.

= 1.8.37 =
* **Elementor popups:** Re-wrap `showPopup` after Geo Elementor session reopen guards; clear `egp_closed_*` and Elementor disable storage for geo-allowed popups; add `forceOpenViaModal()` fallback when `showModal()`/`showPopup()` no-op.

= 1.8.36 =
* **Elementor popups:** Inject page-load triggers into allowed popup wrapper settings when theme conditions strip them; print forced popups under popup location context with CSS enqueued; JS attaches late popup documents to `documentsManager`, clears Geo Elementor session reopen blocks, and prefers `showModal()` before `showPopup()`.

= 1.8.35 =
* **Elementor popups:** Stop skipping force-print when Elementor marks a popup printed without emitting HTML; merge geo-allowed popups into theme-builder location cache so page-load triggers survive on variant URLs; expanded force-print trace and JS markup/doc detection.

= 1.8.34 =
* **Elementor popups:** Force-print geo-allowed popup documents when theme-builder conditions omit them on variant URLs; hook `before_do_popup`/`after_do_popup`; dedupe fallback retries; log modal presence when targeting debug is on.

= 1.8.33 =
* **Elementor popups:** Inject geo-allowed popups into Elementor's popup location before footer render (restores modal markup and page-load triggers on variant URLs); retry variant page-load fallback until popup opens or attempts exhaust; console debug when targeting debug is on.

= 1.8.32 =
* **Elementor popups:** Fix page-load trigger detection for Elementor Pro switcher storage (`page_load: yes`); variant route fallback now activates when On Page Load is enabled.

= 1.8.31 =
* **Elementor popups:** Fail-open when `rwgc_show` is true (never block `showPopup`/`triggerPopup`/`showModal`); block only explicit denied popup IDs; robust popup ID resolution from Elementor call args; variant route page-load fallback when Elementor does not auto-open; expanded popup debug fields (variant key, page-load trigger, geo allowed).
* **Variant rules:** `RWGC_Variant_Rule_Applications` — provenance metadata, popup reference discovery, sync-to-popup action, orphan health list, fail-closed evaluation for archived/missing variant rules, admin variant application panel on visibility rule edit.

= 1.8.30 =
* **i18n (WP 6.7):** Defer target-type registry init and admin route/section/module registration from `rwgc_loaded` (plugins_loaded) to `init`, so provider/section labels never translate before `init` (fixes `_load_textdomain_just_in_time` notices on English installs with no .mo files).
* **Elementor popups:** Add `RWGC Popup Config Trace` debug log (when targeting debug is on) listing detected geo popups and per-popup show/block decisions.

= 1.8.29 =
* **Elementor popups:** Remove theme-location template_id filter that prevented blocked popups from loading (restores geo-matched popup display); keep JS/CSS guards for blocked IDs only.
* **i18n:** Load all suite textdomains on `init` priority -1; satellites queue via `plugins_loaded` priority 6 after Geo Core boots (fixes alphabetical load order and WP 6.7 JIT notices).

= 1.8.28 =
* **Elementor popups:** Stop flash-then-hide (remove onShow force-close); patch runtime in `wp_head`; skip geo-blocked popup templates at theme location; trust server `rwgc_show` for allowed popups.
* **i18n:** Load suite textdomains on `plugins_loaded` and `init` priority 1; defer Elementor control registration to `init` priority 20 (WP 6.7 JIT notices).

= 1.8.27 =
* **Elementor popups:** Stop calling `document.disable()` on every hide (was blocking geo-matched popups from triggering). User close uses a short in-memory reopen suppress only; geo still enforced server-side.

= 1.8.26 =
* **Elementor popups (close):** On dismiss, call Elementor `document.disable()` so timing triggers stop re-opening; resolve popup ID from modal `#elementor-popup-modal-{id}`; close capture runs `forceClosePopup()` (hide + disable); lazy-patch documents on `elementor/popup/show`; keep patching documents for 30s.

= 1.8.25 =
* **Critical:** Fix PHP parse error in Elementor popup script (`\$document` / `\$el` in inline JS). Load suite textdomains at `init` priority -1 (before Elementor `init`) to stop WordPress 6.7 `_load_textdomain_just_in_time` notices. Commerce hub blurbs use `reactwoo-geocore` domain.

= 1.8.24 =
* **Targeting rules admin:** Separate portable rule library from builder-attached rules; source labels Elementor / Gutenberg / Geo Core / Geo Commerce; deep-link edit actions for Elementor and Gutenberg surfaces.

= 1.8.23 =
* **Elementor popups (close fix):** Patch each popup document’s `showModal` and modal `hide` (Elementor’s real open/close path). Listen for `elementor/popup/show|hide` on `elementorFrontend.elements.$document` (not only `jQuery(document)`). Capture close clicks on overlay/X; force-close via `documentsManager`. Removed antiflash `pointer-events:none` that could block close on mismatched IDs.

= 1.8.22 =
* **Elementor popups:** Respect visitor dismiss (session) so geo-allowed popups do not immediately re-open after close; patch `closePopup` and `elementor/popup/hide`. Removed `preventDefault` on show events that could break close. Shorter blocked-popup DOM guard interval.

= 1.8.21 =
* **Release workflow:** Document single-push releases (`main` + tag); CI tests run on pull requests only so tag publishes are not preceded by a redundant main-branch test job.
* **Agent guidance:** Added `.cursor/rules/release.mdc` for reliable Windows/git release steps.

= 1.8.20 =
* **MaxMind in Integrations:** New **Integrations → System services → MaxMind (GeoLite2)** screen for Account ID, license key, database download/refresh, manual `.mmdb` upload, cache clear, and visitor detection tests.
* **Admin IA:** Removed MaxMind/database controls from Settings and legacy Tools; updated dashboard, onboarding, targeting notices, and global admin notices to point at the integration screen.

= 1.8.19 =
* **Country picker UX:** Elementor widgets/sections now use the same Elementor SELECT2 country control as pages and popups (no Ctrl/Cmd native list).
* **Gutenberg post panel:** Country selection uses search-and-add combobox + chip list (aligned with Geo Content block).

= 1.8.18 =
* **Targeting conformity:** Shared `RWGC_Surface_Settings` normalization and `RWGC_Elementor_Geo_Controls` for split country targeting + visibility rules across Elementor elements, Gutenberg Geo Content block, post Geo visibility panel, and `[rwgc_if]` surface attributes.
* **Evaluation:** All surfaces route through `RWGC_Targeting_Surface_Evaluator` with independent layers (AND when both enabled).

= 1.8.9 =
* **Popup suppression hardening:** Added runtime event-level popup guard (`elementor/popup/show`) with forced close fallback when a popup should be blocked by geo targeting.
* **Elementor runtime compatibility:** Handles popup trigger paths that bypass direct `showPopup`/`triggerPopup` hooks.

= 1.8.8 =
* **Popup targeting fix:** Strengthened popup frontend patch to handle Elementor Pro fallback trigger paths and enforce country matching when Geo targeting is enabled.
* **Popup behavior:** Popups configured for one country no longer leak to other countries due to uncaught runtime trigger paths.

= 1.8.7 =
* **Visibility mode UX:** User-facing "Show only when rules match" / "Hide when rules match" labels added across Elementor document/element targeting and Gutenberg post targeting.
* **Canonical model:** Core now normalizes visibility mode to `show_if` / `hide_if` with backward compatibility for legacy `show` / `hide`.
* **Cross-surface evaluation:** Shared helpers now drive Elementor elements, Elementor documents, Gutenberg post content, and popup fallback filtering consistently.

= 1.8.6 =
* **Targeting action model:** Canonical visibility mode values now use `show_if` / `hide_if` (with backward compatibility for `show` / `hide`).
* **Elementor + Gutenberg parity:** Added user-friendly "Visibility mode" labels and shared mode evaluation helpers across document/element/post surfaces.
* **Popups:** Legacy popup targeting fallback now respects visibility mode (`show_if`/`hide_if`) while keeping existing triggers.

= 1.8.5 =
* **Fix:** Legacy Elementor popup geo rules work without Geo Elementor (`RWGC_Elementor_Popups`).
* **Elementor:** Saved visibility rule library dropdown before the rule builder; improved rule builder mount for advanced targeting.

= 1.8.4 =
* **Fix:** Elementor element geo controls use `RWGC_Countries::get_options()` (fatal on `get_countries()`).

= 1.8.3 =
* **Integrations:** Native Elementor element-level geo controls and server-side visibility (`includes/integrations/elementor/`). Gutenberg post-level geo panel (`RWGC_Gutenberg_Post_Geo`). Geo Elementor defers duplicate controls when Core is active.
* **Docs:** Migration plan updated for Phase 2/4/5 partial delivery.

= 1.8.2 =
* **Settings:** GeoCore Pro tab for licence and setup; Elementor integration tab (free adapter settings only). Legacy Geo Elementor licence screen removed from Settings grouping.
* **Targeting:** Rule builder page-version input no longer loses focus; visibility rule library picker; `rwgc_advanced_targeting_enabled` filter; portable controls gated on GeoCore Pro.
* **Docs:** GeoCore Pro product migration plan (`docs/MIGRATION-GEOCORE-PRO-PRODUCT.md`).

= 1.8.1 =
* **Fix:** Page Version URL condition no longer crashes the rule builder (`escapeHtml` scope).

= 1.8.0 =
* **Page Version URL:** Branded `/_gc/{version-name}` routes on existing pages for portable targeting (Elementor, Gutenberg, rule builder). Content with a Page Version URL condition shows only on that version URL, not on the base page.

= 1.7.9 =
* **Admin UX:** Integrations scoped by category (Analytics, Advertising, APIs, Ecommerce, Content builders, System services) with per-provider screens; split Google Analytics and Google Ads; Meta placeholder; API keys without setup wizard. Targeting → Rules unified index. Experiences → Variants shows experiment variants only; Dynamic content grouped by content type. Campaign and audience insights empty states. See `RWGC_Admin_Integrations_Nav` and `docs/geo-admin-ia-checklist.md`.

= 1.7.8 =
* **CI:** Fix Composer lock compatibility for GitHub Actions PHP 8.2 by pinning Composer platform PHP and locking `doctrine/instantiator` to a PHP-8.2-compatible release.

= 1.7.7 =
* **Admin IA:** Intent-based suite navigation (Overview, Targeting, Experiences, Commerce, Insights, Integrations, Settings). New section hubs and routes for Audiences, Campaigns, Gutenberg, WooCommerce, Merchandising, Availability, and Experience performance. Internal/detail screens hidden from primary section tabs via `is_section_nav`. See `docs/geo-admin-ia-checklist.md`.

= 1.7.6 =
* **Phase 4 polish:** Improved responsive app shell navigation (mobile horizontal module nav, active tab auto-centering for module/section/settings strips, reduced-motion support).

= 1.7.5 =
* **Phase 4:** Platform setup checklist on Overview (visibility library, commerce optional); shell Overview hides duplicate stats; Settings hub Geo Core quick links; Integrations section tabs.

= 1.7.4 =
* **Phase 3:** Integrations section hub (`rwgc-integrations-hub`) with sync health and connection cards; `rwgc_get_platform_integrations()` / `rwgc_get_settings_providers()` / `rwgc_get_platform_sync_status()` APIs; improved topbar sync hints.

= 1.7.3 =
* **Phase 2:** Google Ads `campaign_id` URL capture (`campaignid`, `gad_campaignid`); Insights hub groups reports by provider when platform shell is active.
* **Phase 3 (start):** Grouped Insights section hub (Core, Geo AI, Geo Optimise cards).

= 1.7.2 =
* **Phase 2:** Core visibility rules library (`rwgc_visibility_rule` CPT) under Targeting → Visibility rules with shared rule builder; `rwgc_get_visibility_rule_set()` helper.

= 1.7.1 =
* **Phase 2:** Commerce section hub; unified visibility rule builder on block editor, Elementor, and geo rules; shell-aware hub pages; Geo Commerce portable rules integration; PHPUnit targeting tests and CI workflow.

= 1.7.0 =
* **Phase 2 (start):** Targeting section hub (`rwgc-targeting-hub`) with experience cards; Geo Optimise experiment reports under Targeting; hub pages skip legacy inner nav when the platform shell is active.

= 1.6.1 =
* **Platform (Phase B):** Skip duplicate virtual bind for the top-level `rwgc-dashboard` slug (owned by `add_menu_page()`).
* **Satellites:** Commerce, Geo AI, Geo Optimise, and Geo Elementor skip legacy inner nav when the platform shell is active.

= 1.6.0 =
* **Platform (Phase B):** `rw_geo_register_app_route()` registers shell-only hub pages by default (`register_wp_submenu` false) — no wp-admin flyout rows for satellite detail screens; virtual page binding preserves direct `?page=` access.
* **Opt-in wp submenu:** Pass `register_wp_submenu` true (or legacy `show_in_wp_sidebar`) for routes that should appear under ReactWoo Geo in wp-admin (e.g. Setup wizard).

= 1.5.5 =
* **Fix:** Hub section links (Targeting, Commerce, Insights, Integrations, Settings) work again after flyout collapse — removed submenu rows are re-registered for WordPress access checks while staying hidden in the sidebar.

= 1.5.4 =
* **Fix:** Restore collapsed wp-admin hub flyout — detail submenu rows are removed again (Setup wizard optional via `rwgc_admin_visible_submenu_slugs`); direct `?page=` URLs stay allowed; collapse CSS loads on all admin screens.

= 1.5.3 =
* **Settings UX:** One top tab per satellite (Geo Core, Elementor, Optimise, Commerce, Geo AI) with license-first sub-tabs per provider; settings home shows one card per satellite.
* **Admin shell:** Suppress third-party wp-admin notices on hub screens; Geo Core notices render inside the platform shell only.
* **Integrations:** GeoCore Pro content uses the same centred max-width layout as other shell sections.

= 1.5.2 =
* **Fix:** Geo Core hub parent menu registers at `admin_menu` priority 5 so satellite pages (Geo Elementor, Commerce, etc.) register submenus after the parent exists.

= 1.5.1 =
* **Fix:** Collapsed hub sidebar no longer calls `remove_submenu_page()` — direct links (Commerce, Insights home, Settings home, GeoCore Pro) work again; flyout is hidden with CSS only.
* **Fix:** Default hub submenu capability uses `RWGC_Admin::required_capability()` (shop managers with `manage_woocommerce`).

= 1.5.0 =
* **Platform UX (Phases 1–4):** ReactWoo Geo app shell enabled by default — six goal sections (Overview, Targeting, Commerce, Insights, Integrations, Settings), collapsed wp-admin flyout, live sync topbar, shared design tokens (`rwgc-design-system.css`).
* **Routes:** Core and satellites register via `rw_geo_register_app_route()`; Insights home and Settings home hub screens; `rw_geo_app_url()` resolves route slugs.
* **Overview:** Platform setup checklist, sync card, section shortcuts; upgrade cards for GeoCore Pro gaps.
* **Targeting:** Experience targeting page, rule builder tab labels, portable targeting improvements.
* **Hooks:** `rwgc_onboarding_platform_steps`, `rwgc_section_hub_cards`, `rwgc_platform_overview_section_links`, `rwgc_uses_platform_shell()`.
* **Docs:** `docs/geo-platform-ux-architecture.md` (authoritative UX plan).

= 1.4.2 =
* **Fix:** Restore working wp-admin navigation — submenu ordering is back by default, app shell UI is off by default, menu collapse uses `remove_submenu_page`, and plugins.php redirect flags are cleared earlier.

= 1.4.1 =
* **Fix:** Restore a dashboard submenu row so the **ReactWoo Geo** top-level menu stays clickable; stop activation redirect from trapping **Plugins** (deactivate works again).

= 1.4.0 =
* **Admin UX:** Unified **ReactWoo Geo** wp-admin entry with collapsed sidebar flyout; in-app **app shell** (module + section navigation) on hub screens; route registry API (`rw_geo_register_app_route`, `rw_geo_app_url`). Setup and Suite home screens registered under the hub.

= 1.3.40 =
* **Satellite shell:** Shared **`RWGC_Admin_UI::render_inner_nav()`**, **`rw_geo_render_inner_nav()`**, and hub breadcrumb styles for extension screens under Geo Core.

= 1.3.39 =
* **Admin hub:** The free plugin keeps the wp-admin sidebar name **Geo Core** (not a generic “ReactWoo Geo” rebrand). New **`RWGC_Admin_Platform`** orders submenus: core screens first, **Geo extensions** heading, then extension home links (Elementor, Commerce, Optimise, AI). Helpers: **`rwgc_admin_menu_parent()`**, **`rw_geo_register_admin_submenu()`**.

= 1.3.38 =
* **Targeting UX:** Rule builder playground on Targeting admin; visibility mode, selected chips, and clearer summaries; block editor toggle for advanced rules; Elementor mounts only when portable rules are enabled.

= 1.3.37 =
* **Fix:** Ship `RWGC_Context_Snapshot_Formatter` required since 1.3.36 (fixes fatal error on boot when the class file was missing from the package).

= 1.3.35 =
* **Dashboard:** Satellite quick actions (Geo AI, Geo Commerce, Geo Optimise) are built from **`RWGC_Module_Registry`** so filtered module rows and `admin_url` stay consistent with Suite readiness; new filter **`rwgc_dashboard_quick_actions`** for last-mile tweaks.

= 1.3.21 =
* **Satellite updates:** Fire actions on `/api/v5/updates/check` (no JWT, transport error, raw HTTP) so commercial plugins can surface 401 and other failures in admin (Geo AI 0.4.23+ shows results on Settings).

= 1.3.20 =
* **Getting Started:** Stepper sits in a suite card; wizard and footer actions use `rwgc-btn` (consistent radius and spacing) instead of default WordPress buttons.
* **Suite shell:** Stacked section cards use adjacent-sibling top margin (and grid gap where applicable) so spacing is reliable when a single card sits in a grid wrapper.

= 1.3.19 =
* **Admin (suite shell):** Vertical spacing between stacked `.rwgc-card` sections, extra space below inner tab nav, and bottom padding on suite wraps so dashboards feel less cramped. Applies to all satellites using the shared shell.

= 1.3.18 =
* **CI:** Publish workflow builds `/api/v5/updates/publish` JSON with Python (proper escaping; charset on Content-Type) to avoid OpenResty 415.

= 1.3.16 =
* **Targeting foundation:** Extensible **`RWGC_Target_Registry`** with provider interfaces (geo, device, language, time, commerce, analytics, weather), context resolution and snapshot helpers, availability checks, simulator, and admin screens for **Target types**, **Target providers**, and **Context preview** (for satellite integration and debugging).

= 1.3.15 =
* **Satellite updater auth:** `RWGC_Satellite_Updater` now supports per-plugin bearer-token and API-base callbacks so commercial satellites can update with their own independent credentials instead of sharing Geo Core's runtime license/token path.

= 1.3.11 =
* **Admin:** Geo Core Settings form (`options.php`) uses `option_page_capability_rwgc_settings_group` and `register_setting` capability matching `RWGC_Admin::required_capability()` so shop managers can save settings.

= 1.3.10 =
* **Admin:** Geo Core menu and screens align with Geo Elementor: `manage_options`, or `manage_woocommerce` for WooCommerce shop managers who do not have `manage_options`. Filter: `rwgc_required_capability`.

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

