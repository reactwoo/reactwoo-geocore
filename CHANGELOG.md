# Changelog

All notable changes to **reactwoo-geocore** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

Older entries through **0.1.x**–**1.8.x** are maintained in `readme.txt` (WordPress.org changelog section).
