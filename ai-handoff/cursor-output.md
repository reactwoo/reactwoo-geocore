# Cursor output

## Status

**done** — Fixed critical legacy inline routing data loss / wrong-page serving on `main`/`v1.8.120` (`87fb935`).

## Bug and impact

1. **Meta box wipe:** Saving a page via the Geo routing meta box cleared `_rwgc_route_country_page_id` because the UI no longer posts that field. Sites still on Phase-2 inline master mapping lost geo redirects on a normal title/content Update.
2. **Suite shadow:** Creating/linking a Suite country variant preserved the master’s same-country inline mapping (`priority` 55), which outranks the new Suite child (`priority` 50). After publishing the Suite variant, matching visitors kept redirecting to the old inline page.

## Root cause

- `RWGC_Admin::save_page_meta_box()` defaulted missing `rwgc_route_country_page_id` / `rwgc_route_default_page_id` to `0` and always wrote them.
- `RWGC_Variant_Manager::{create_country_variant,link_existing_variant}()` used `array_merge( $mconf, … )`, keeping stale inline ISO2→page ids that `RWGC_Legacy_Route_Mapper` still projects at priority 55.

## Fix

- `RWGC_Routing::route_config_from_meta_box_request()` preserves unposted legacy inline fields.
- `RWGC_Routing::master_config_for_suite_variant()` clears same-country inline mapping when attaching a Suite child.
- Wired both into admin save + Suite create/link paths.
- Added `tests/Engine/RWGCLegacyInlineRoutingTest.php`.

## Files changed

- `includes/class-rwgc-routing.php`
- `includes/class-rwgc-admin.php`
- `includes/class-rwgc-variant-manager.php`
- `tests/Engine/RWGCLegacyInlineRoutingTest.php`
- `ai-handoff/cursor-output.md`

## What was not changed

- Known open issues (#17–#47), including visibility-rule CPT guard (#18), Elementor SWITCHER (#45), Suite Elementor meta copy (#47).
- Pro-condition strip-on-read (documented intentional behavior in `docs/TARGETING-RULES-PLAN.md`).
- Shared redirect-loop transient keyed by IP+UA (design tradeoff; not patched).

## Commands run

- `php -l` on touched PHP files — OK
- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Engine/RWGCLegacyInlineRoutingTest.php` — OK (4 tests, 11 assertions)

## Remaining

- None for this fix. Highest remaining unmerged criticals still tracked in automation memory (#25, #26, #28, #31/#37, #33, #35/#42, #40, #41, #43, #44, #45, #46, #47, #17/#18, #21).
