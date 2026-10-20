# Changelog

All notable changes to **reactwoo-geocore** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.9.17] - 2026-10-20

### Added
- WordPress 6.9+ Abilities (MCP) exposes three read-only admin tools: list capabilities, describe one capability, and read site context (Geo ready, licensed satellites, active experiences). Conditions and actions stay on the Decision Runtime.

## [1.9.16] - 2026-10-19

### Fixed
- Status messages use `wp_strip_all_tags()`. MaxMind directory checks, creation, and archive cleanup use `wp_is_writable()`, `wp_mkdir_p()`, `wp_delete_file()`, and `WP_Filesystem_Direct`.

## [1.9.15] - 2026-10-19

### Fixed
- Fix: MaxMind download/upload/copy no longer overwrites settings an admin saved while it ran

## [1.9.14] - 2026-10-17

### Fixed
- Country lookup no longer returns `fallback_db_missing` when the database is still in the dated folder an older version extracted (`uploads/reactwoo-geocore/GeoLite2-Country_YYYYMMDD/GeoLite2-Country.mmdb`) and the saved `db_file_path` is empty or points at a file that no longer exists. `RWGC_MaxMind::get_db_path()` now uses the saved file, then `uploads/reactwoo-geocore/GeoLite2-Country.mmdb`, then the newest valid dated `GeoLite2-Country.mmdb`. A dated database it finds is copied once to the single-file path (the dated folder stays), and the path is saved.
- A `fallback_db_missing` or `fallback_no_reader` result is no longer kept in the per-IP cache for the cache TTL, so visitors get their real country as soon as the database is back.
- The automatic MaxMind download is scheduled after a plugin auto-update too. WordPress does not run the activation hook on an update, so 1.9.13 only scheduled `rwgc_maxmind_auto_update` on activation or a credentials save. Geo Core now syncs the schedule on `init` when the plugin version changes (`rwgc_maxmind_schedule_version`), when the event is missing while automatic updates are on, or when it is still queued while they are off. Duplicate events from two racing requests collapse to one.
- A successful download, a manual upload, or the one-time move of a dated database bumps `rwgc_cache_version`. Cached per-IP lookups made with the old or missing file are dropped straight away instead of lasting for the cache TTL.
- The MaxMind screen no longer shows an old 401 next to a success notice. Saving credentials makes no request to MaxMind; the red box was the stored error from an earlier download, and 1.9.13 cleared it only when the credentials changed. A credentials save now always clears that message. "Download / refresh database" and "Clear geo cache" now run on `admin_init` and redirect, instead of running inside the page after the shell had already printed the stored error. A successful download or upload clears both the last error and the last failure time. The error box now says it is the last download failure, with its time. The upload notice now shows after the redirect.
- Geo Core admin screens are readable again when the computer is set to dark mode. 1.9.13's `prefers-color-scheme: dark` styles made headings, strong text and descriptions light while `rwgc-suite.css` kept the cards white, and they left WordPress's dark labels on dark form tables. That gave white-on-white headings (Account credentials, Resolution Hub, Geo Assistant, Product license), dark-on-dark labels (QUIC.cloud, Cache results, MaxMind, Account ID, License key) and an unreadable success notice. The partial dark theme is removed, cards and notices set their own text colour from `--rwgc-canvas-*` / `--rwgc-surface-*` tokens, and muted text (#6b7280 → #4b5563), the assistant avatar (#2563eb → #1d4ed8) and the success tick (#059669 → #047857) now meet WCAG AA 4.5:1. Geo Commerce screens use these stylesheets too, so they are fixed with no change to Commerce.

### Security
- `GET /wp-json/reactwoo-geocore/v1/location` no longer returns the visitor's `ip` to the public. It stays in the response only for a user who can `manage_options`, for debugging, which needs a REST nonce when you use cookie auth. The response is per visitor, so it now sends `Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private`, `Pragma: no-cache`, `Expires`, `Vary: Cookie` and `X-LiteSpeed-Cache-Control: no-cache`, and it fires `litespeed_control_set_nocache`. A page cache or CDN can no longer serve one visitor's country (or IP) to another. Nothing in Geo Core, Geo Core Pro or Geo Commerce reads `ip` from this response.

## [1.9.13] - 2026-10-17

### Fixed
- Automatic MaxMind updates run on a twice-weekly schedule, with jitter, when the setting is on and credentials are saved. The schedule is removed on deactivation or when the setting is off, and a run with no credentials does nothing.
- The MaxMind screen records and shows the last successful download and the last failed download.
- Saving MaxMind credentials returns to the screen with the “MaxMind credentials saved.” notice.
- The license key field shows only the last four characters. Leaving it blank or unchanged keeps the stored key.
- The download keeps GeoLite2-Country.mmdb from the newest dated directory in the archive, opens it with the MaxMind reader, and replaces the live file by rename.
- The MaxMind screen no longer scrolls sideways on a 390px-wide phone.
- Extracting a MaxMind archive no longer uses an unreadable system temp directory. A failed extract is saved as the last error, temporary files are removed, and the live database is left in place.
- A failed download keeps its error message, including a MaxMind 401, next to the failure time until a later download succeeds.

## [1.9.12] - 2026-10-17

### Fixed
- MaxMind credentials: strip non-breaking/zero-width spaces from a pasted Account ID or license key, reject a non-numeric Account ID before calling MaxMind, and explain a 401 in plain words.
- Saving Settings → General no longer wipes the MaxMind Account ID, license key, auto-update flag and database path.
- Saving new MaxMind credentials clears the previous download error instead of showing it on every Geo screen.
- MaxMind credential inputs turn off mobile autocapitalize, autocorrect and autofill.

### Changed
- Country lookup uses GeoIP2 3.4.0 against the local GeoLite2 database. The MaxMind licence-key download is unchanged.
- The WordPress.org package no longer includes MaxMind's C extension sources (`vendor/maxmind-db/reader/ext/`).

## [1.9.11] - 2026-10-17

### Fixed
- Tapping ReactWoo Geo in the WordPress admin menu on a phone opens the hub.
- The in-page admin menu sits below the admin bar and stays within the screen width.

## [1.9.10] - 2026-10-16

### Fixed
- A rejected non-https iframe is removed on its own. Later content and https embeds stay on the page.

## [1.9.9] - 2026-10-15

### Fixed
- Geo Content keeps https embeds and forms across the block, shortcode and Elementor while still escaping unsafe markup.

## [1.9.8] - 2026-10-15

### Changed
- Readme, rule-builder cards, and the visibility-rules capability now say Geo Core includes country and country-group rules. UTM, click-ID, device, language, and time rules are described as Geo Core Pro.
- Geo Core Pro can add rule-builder cards through `rwgc_target_type_cards`.
- The admin shell aligns the active tab without a smooth scroll, so Chrome no longer aborts the page view transition when a Geo admin screen opens.

## [1.9.7] - 2026-10-14

### Changed
- Geo Core evaluates country and country-group visibility rules. Device, language, schedule, campaign, visitor, and page conditions are owned by Geo Core Pro.
- The rule builder offers country targeting on its own. Geo Core Pro adds its conditions to the same builder when it is entitled.
- Screens for audiences, campaigns, experiments, AI review, and add-ons are registered only when the plugin that provides them is active.
- Saving a rule keeps conditions owned by another plugin. They do not match until that plugin registers a resolver.

### Fixed
- The Experience Slot block ships `editor.asset.php` so WordPress 6.2 does not log `register_block_script_handle`.

## [1.9.6] - 2026-10-14

### Fixed
- Inline admin and front-end CSS and JavaScript are registered with `wp_enqueue_script`, `wp_enqueue_style`, `wp_add_inline_script`, and `wp_add_inline_style`.
- Nonces are passed through `sanitize_text_field( wp_unslash() )` before `wp_verify_nonce`.
- `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, and `$_SERVER` reads are unslashed and sanitized.
- Block, shortcode, and Elementor markup is escaped before it is returned or printed.
- MaxMind database uploads use `wp_handle_upload`, then a type, size, and MaxMind marker check.

### Changed
- The WordPress.org package includes `composer.json`. Directory icons under `assets/` and `vendor/composer/ca-bundle/res/cacert.pem` are left out. The certificate file is only a fallback for the MaxMind web-service client, which Geo Core does not construct. Country lookup uses the local database, and downloads use the WordPress HTTP API.
- Page-version routing stays out of this plugin. A stored `page_version_url` condition is preserved on save and is not shown in the rule builder unless Geo Core Pro is active. It does not match until that class is loaded.
- Anonymous Cloud telemetry treats only `true`, `1`, and `"1"` as consent. A checkbox that is not exactly `1` stores `0` and clears `rwgc_vid`.

## [1.9.5] - 2026-10-13

### Removed
- Customer-facing GeoElementor add-on cards, module label, and brand image.
- Loading MaxMind from a GeoElementor install, copying its MaxMind key, and registering the old geo_rule post type.

## [1.9.4] - 2026-10-13

### Changed
- The page-version redirect, `/_gc/` rewrite, and page-version editor moved to Geo Core Pro. Geo Core keeps stored pages and `_rwgc_route_*` settings and does not run that redirect.

## [1.9.3] - 2026-10-13

### Changed
- Full-page country versions are no longer capped at one page inside Geo Core, and the editor for them appears only when Geo Core Pro is entitled. A Decision Cloud personalisation grant counts as that entitlement.
- The WordPress.org build does not boot page-version redirects. Stored routing settings and pages are not deleted. The ReactWoo update channel still runs routes that are already saved.
- Includes the Geo Core 1.9.2 fixes for Cloud telemetry opt-out and the Geo Content country list on WordPress 6.2–6.4.

## [1.9.2] - 2026-10-13

### Fixed
- Anonymous Cloud telemetry stays off for any stored value other than an explicit opt-in (`true`, `1`, or `"1"`). That covers `'0'`, `'no'`, `'off'`, `false`, and `''`. WordPress returns a saved `0` as the string `"0"`, which a boolean cast treated as consent.
- The Geo Content country list loads on WordPress 6.2–6.4. Those versions register a different editor-script handle, so the country options are now attached to the handle WordPress actually enqueued.

## [1.9.1] - 2026-10-09

### Fixed
- Geo Content visibility-rule controls render when advanced targeting is on. The block reads `advancedTargeting` or `advanced_targeting`, and the editor payload sends both.
- Geo Content can contain inner blocks. The server shows or hides that inner HTML with the block’s targeting. Blocks saved as self-closing comments stay valid.
- The Geo Content rule builder mounts when a block with visibility rules is selected, including after the editor reloads and when the block is selected again. The JSON textarea remains only if the builder script does not load.
- Choose from library lists published visibility rules only, the same as the saved-rule select.

## [1.9.0] - 2026-10-09

### Fixed
- An uploaded country database no longer shows the MaxMind license-key warning. The warning appears only when there is no usable database and no license key to download one.
- The Geo Content block registers its editor script, so the block editor can insert it.
- The WordPress.org zip omits vendor maintainer files such as `dev-bin/release.sh`, and the packager refuses to build if a VCS directory or a `.phar`, `.sh`, `.zip`, `.gz`, `.tgz`, `.tar`, `.rar`, or `.7z` file is still inside.
- Plugin URI is `https://reactwoo.com/geo-core/`, so it is not the same address as the author URI.

### Changed
- Requires PHP 8.1, matching the MaxMind libraries shipped in `vendor/`.
- Anonymous Cloud telemetry, including the `rwgc_vid` cookie, stays off until an administrator opts in on the ReactWoo Cloud screen.
- Request UTM and click-id conditions (`utm_source`, `utm_medium`, `utm_campaign`, `source`, `medium`, `campaign`, `content`, `term`, `gclid`) are part of Geo Core. Audience, weather, and profile conditions still need the separate GeoCore Pro plugin.
- The WordPress.org build does not include the ReactWoo update client. If a Pro add-on calls `RWGC_Satellite_Updater`, that call does nothing instead of stopping the site. Pro add-ons update themselves.

## [1.8.171] - 2026-10-09

### Fixed
- Elementor: the stale-rule editor warning now shows. It attaches after the panel renders, so selecting a widget and opening Geo Visibility shows the notice.
- A deleted or unpublished rule stays selected in the saved-rule picker (`Title (unpublished)` or `Rule #<id> (deleted)`), so Update does not drop the reference.
- The saved-rule picker lists only published rules. A draft or trashed rule appears only as that selected stale option.
- The warning is not rebuilt when its text is unchanged, including while the Geo section is collapsed.

## [1.8.170] - 2026-10-08

### Added
- Editors warn when a widget, section, container, popup, page setting, or Geo Content block still points at a deleted, missing, draft, or trashed visibility rule. The stored reference stays until someone picks another rule or clears it. Visitor-facing behaviour is unchanged.

### Fixed
- The editor status lookup queries the visibility-rule post type directly. `post_type => any` omitted that type, so every saved rule looked deleted. Draft, private, and trashed titles are returned only to users who can edit that rule.

## [1.8.169] - 2026-10-07

### Fixed
- With “My site uses QUIC.cloud CDN” on, a site whose PHP sees a loopback or private address (nginx/php-fpm, Docker, local LiteSpeed proxy) no longer geolocates visitors as the QUIC.cloud edge server.

## [1.8.168] - 2026-10-06

### Added
- Settings → Detection: **My site uses QUIC.cloud CDN** (off by default). When on, a connection from a published QUIC.cloud edge uses the visitor IP in `X-Forwarded-For`. Any other public connection still ignores forwarding headers. The edge list is the copy shipped with the plugin, replaced by a daily background refresh from `https://www.quic.cloud/ips-all?json` when that fetch succeeds. Filter `rwgc_trusted_proxy_cidrs` adds other CDN ranges. `rwgc_visitor_ip` still applies last.

### Fixed
- Elementor: filling the shared country catalogue no longer wipes countries already saved on a control when the editor updates (saved model value is restored; the catalogue fill does not write an empty SELECT2 change).
- GeoIP: client forwarding headers (`X-Forwarded-For`, `CF-Connecting-IP`, `Client-IP`, and similar) are no longer trusted on their own. `CF-Connecting-IP` is used only when the TCP peer is a published Cloudflare range. A private or reserved peer may contribute only the rightmost public forwarding hop. Filter: `rwgc_visitor_ip`.
- LiteSpeed: cache vary groups come from the server-resolved country and page version. Forged `rwgc_cc` / `rwgc_pv` cookies are overwritten and are not registered as vary cookies.
- Visibility rules: looking up a missing, draft, trashed, or deleted library rule no longer recurses until the visitor page fatals. Those rules never match, so “show only if” content stays hidden, “hide if” content stays visible, and an inactive page-variant rule reports `variant_rule_inactive` so the page can fall back to the default.
- Elementor: an unauthenticated `?elementor-preview` request no longer bypasses document geo rules. The preview bypass requires a logged-in user who can edit the document.

## [1.8.167] - 2026-09-29

### Fixed
- Elementor: ship the country catalogue once and fill classic and Atomic country controls from that shared list.
- Elementor V4: keep Geo Visibility selects responsive when Atomic widgets are active.

## [1.8.166] - 2026-09-22

### Fixed
- Elementor builder: removed leftover `$heavy` from document Geo Visibility (undefined-variable warnings could break `elementor_ajax` JSON and leave widgets spinning). Widget Geo Visibility visitor preview is memoized once per request.

## [1.8.165] - 2026-09-21

### Fixed
- First-visit `returning_visitor` / `new_visitor` targeting no longer flips to returning on the next page view or AJAX/Store API request in the same session.
- Geo Core tests on `cursor/*` PRs now complete successfully instead of skipping the required check.

## [1.8.164] - 2026-09-21

### Added
- **Returning / new visitor conditions:** persist a first-seen `rwgc_returning` cookie, evaluate `returning_visitor` / `new_visitor` in the portable rule evaluator, register `visitor.returning` as a Core capability, and resolve it in Decision Runtime context.

### Fixed
- First-visit UTM no longer classified the visitor as returning on the same request. Portable rules using returning/new visitor no longer fail closed for lack of a resolver.

## [1.8.163] - 2026-08-21

### Changed
- PLAN.md production Cloud commerce is on: catalogue SQL, license package `reactwoo-decision-cloud`, and `REACTWOO_CLOUD_BRIDGE_ENABLED` verified. Remaining operator work is settings product IDs if empty, identity Sign in, paid checkout E2E, and Gate E.

## [1.8.162] - 2026-08-20

### Changed
- PLAN.md Local stop-ships closed (Woo E2E, Figma §16 visual screens, §20 shipped defaults). Production Cloud commerce stays off.

## [1.8.161] - 2026-08-20

### Changed
- Gate D live Local loop passed (`docs/architecture/gate-d.md`). Production Cloud commerce stays off.

## [1.8.160] - 2026-08-20

### Added
- **Gate D:** `RWGC_Request_Decision` supplies `reactwoo_current_decision_result` from the cached Cloud manifest (local evaluate, no Cloud HTTP). Experience Slot runtime overlay hydrates variants/slots for the request.

### Changed
- Condition contract accepts portal aliases `op` and `type` (`op` → `operator`, `type` → `capability`).

## [1.8.159] - 2026-08-20

### Changed
- PLAN.md remaining store phases: handover wired into My Account downloads, licence provision reuse, operator catalogue SQL. Production Cloud commerce stays off.

## [1.8.158] - 2026-08-20

### Changed
- Cloud entitlement is grant-OR: a commercially valid Decision Cloud snapshot or a standalone licence can entitle covered capabilities. Connecting Cloud no longer replaces standalone access by itself.
- Canonical commercial model documented in `docs/architecture/PLAN.md`.

## [1.8.157] - 2026-08-17

### Changed
- Cloud Connector default API base is `https://decision.reactwoo.com/api/v1`. Sites that stored the old Google-vault URL (`cloud.reactwoo.com/api/v1`) are migrated automatically. `cloud.reactwoo.com` remains the Google / Reviews vault.

### Added
- ReactWoo Cloud pairing screen is listed under Integrations → System services.

## [1.8.156] - 2026-08-14

### Added
- **Cloud WP20:** Advisory recommendation contract and Cloud cache. Approve/dismiss never write the live manifest.

## [1.8.155] - 2026-08-14

### Changed
- **Cloud WP19:** Candidate-only audience evaluation, lazy context providers, manifest parse memo, condition short-circuit, Cloud HTTP forbidden after `template_redirect`. See `docs/performance/decision-runtime.md`.

## [1.8.38] - 2026-06-09

### Added
- **Geo AI site intelligence:** `RWGC_AI_Snapshot_Builder`, `rwgc_build_ai_snapshot()`, schema v1, sync status, and admin preview (Settings → AI Data Snapshot). Filter `rwgc_ai_snapshot_payload` for satellite snapshot rows.

## [1.8.37] - 2026-06-06

### Fixed
- **Elementor popups:** Re-wrap `showPopup` after Geo Elementor session reopen guards; clear `egp_closed_*` and Elementor disable storage for geo-allowed popups; add `forceOpenViaModal()` fallback when `showModal()`/`showPopup()` no-op.

## [1.8.36] - 2026-06-06

### Fixed
- **Elementor popups:** Inject page-load triggers into allowed popup wrapper settings when theme conditions strip them; print forced popups under popup location context with CSS enqueued; JS attaches late popup documents to `documentsManager`, clears Geo Elementor session reopen blocks, and prefers `showModal()` before `showPopup()`.

## [1.8.35] - 2026-06-06

### Fixed
- **Elementor popups:** Stop skipping force-print when Elementor marks a popup printed without emitting HTML; merge geo-allowed popups into theme-builder location cache so page-load triggers survive on variant URLs; expanded force-print trace and JS markup/doc detection.

## [1.8.34] - 2026-06-06

### Fixed
- **Elementor popups:** Force-print geo-allowed popup documents when theme-builder conditions omit them on variant URLs; hook `before_do_popup`/`after_do_popup`; dedupe fallback retries; log modal presence when targeting debug is on.

## [1.8.33] - 2026-06-06

### Fixed
- **Elementor popups:** Inject geo-allowed popups into Elementor's popup location before footer render; retry variant page-load fallback until popup opens or attempts exhaust; console debug when targeting debug is on.

## [1.8.32] - 2026-06-06

### Fixed
- **Elementor popups:** Fix page-load trigger detection for Elementor Pro switcher storage (`page_load: yes`); variant route fallback now activates when On Page Load is enabled.

## [1.8.31] - 2026-06-06

### Fixed
- **Elementor popups:** Fail-open when `rwgc_show` is true; block only explicit denied popup IDs; robust popup ID resolution; variant route page-load fallback when Elementor does not auto-open; expanded popup debug fields.
- **Variant rules:** `RWGC_Variant_Rule_Applications` — provenance metadata, popup reference discovery, sync-to-popup action, orphan health list, fail-closed evaluation for archived/missing variant rules, admin variant application panel on visibility rule edit.

## [1.8.30] - 2026-06-06

### Fixed
- **i18n (WP 6.7):** Defer target-type registry init and admin route/section/module registration from `rwgc_loaded` to `init`.
- **Elementor popups:** Add `RWGC Popup Config Trace` debug log when targeting debug is on.

## [1.8.29] - 2026-06-06

### Fixed
- **Elementor popups:** Remove theme-location template_id filter that prevented blocked popups from loading; keep JS/CSS guards for blocked IDs only.
- **i18n:** Load all suite textdomains on `init` priority -1; satellites queue via `plugins_loaded` priority 6.

## [1.8.28] - 2026-06-06

### Fixed
- **Elementor popups:** Stop flash-then-hide; patch runtime in `wp_head`; skip geo-blocked popup templates at theme location; trust server `rwgc_show` for allowed popups.
- **i18n:** Defer Elementor control registration to `init` priority 20.

## [1.8.27] - 2026-06-06

### Fixed
- **Elementor popups:** Stop calling `document.disable()` on every hide (was blocking geo-matched popups from triggering).

## [1.8.26] - 2026-06-06

### Fixed
- **Elementor popups (close):** On dismiss, call Elementor `document.disable()` so timing triggers stop re-opening; resolve popup ID from modal; close capture runs `forceClosePopup()`.

## [1.8.25] - 2026-06-06

### Fixed
- **Critical:** Fix PHP parse error in Elementor popup script. Load suite textdomains at `init` priority -1.

## [1.8.24] - 2026-06-06

### Changed
- **Targeting rules admin:** Separate portable rule library from builder-attached rules; source labels Elementor / Gutenberg / Geo Core / Geo Commerce; deep-link edit actions.

## [1.8.23] - 2026-06-06

### Fixed
- **Elementor popups (close fix):** Patch each popup document's `showModal` and modal `hide`; listen for `elementor/popup/show|hide` on `elementorFrontend.elements.$document`.

## [1.8.22] - 2026-06-06

### Fixed
- **Elementor popups:** Respect visitor dismiss (session) so geo-allowed popups do not immediately re-open after close.

## [1.8.21] - 2026-06-06

### Changed
- **Release workflow:** Document single-push releases (`main` + tag); CI tests on pull requests only.

## [1.8.20] - 2026-06-06

### Added
- **MaxMind in Integrations:** New **Integrations → System services → MaxMind (GeoLite2)** screen for credentials, download/refresh, manual upload, cache clear, and visitor tests.
- **Admin IA:** Removed MaxMind controls from Settings and legacy Tools.

## [1.8.19] - 2026-06-06

### Changed
- **Country picker UX:** Elementor widgets/sections use SELECT2 country control; Gutenberg post panel uses search-and-add combobox + chip list.

## [1.8.18] - 2026-06-06

### Changed
- **Targeting conformity:** Shared `RWGC_Surface_Settings` and `RWGC_Elementor_Geo_Controls`; all surfaces route through `RWGC_Targeting_Surface_Evaluator`.

## [1.8.0] - 2026-06-06

### Added
- **Page Version URL:** Branded `/_gc/{version-name}` routes on existing pages for portable targeting.

## [1.7.0] - 2026-06-06

### Added
- **Phase 2:** Targeting section hub; visibility rules library (`rwgc_visibility_rule` CPT); unified rule builder; Commerce portable rules integration.

## [1.5.0] - 2026-06-06

### Added
- **Platform UX:** ReactWoo Geo app shell — six goal sections, collapsed wp-admin flyout, live sync topbar, `rw_geo_register_app_route()`.

## [1.3.0] - 2026-06-06

### Added
- **Geo Suite shell (MVP):** Suite Home, Getting Started, workflow launchers, variant creation flow, activity log.

## [1.0.0] - TBD

### Note
- Public **1.0.0** target; current stable development line is **1.8.x** (near RC).

## [0.1.0] - 2026-06-06

### Added
- Initial beta: MaxMind detection, shortcodes, REST location, Gutenberg block, Elementor baseline, page variant routing.

---

Older readme changelog entries that are not repeated in the current `readme.txt` remain in the Git history of that file.
