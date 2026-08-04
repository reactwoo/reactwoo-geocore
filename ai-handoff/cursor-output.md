# Cursor output

## Status

**done** — Critical fix for Atomic Geo country targeting fail-open after chips schema change.

## Bug and impact

Atomic documents saved with Geo Core **1.8.122–1.8.123** stored `egp_countries` as `{ $$type: "string", value: "US,GB" }`. Commit `c7394e6` changed the Atomic schema to `string-array` only. Elementor's props resolver returns `null` when `$$type` ≠ schema key, so `get_atomic_settings()` dropped the country list while leaving country targeting enabled. Empty countries fail open for `show_if` (geo-restricted content shown worldwide) and fail closed for `hide_if` (content hidden from everyone).

## Root cause

`RWGC_Elementor_Atomic_Geo::filter_props_schema()` always overwrote `egp_countries` with `String_Array_Prop_Type`. Frontend preferred resolved Atomic settings and never re-read the raw legacy string envelope.

## Fix

- Atomic countries schema is now a `Union_Prop_Type` of `string-array` + legacy `string` (chips still write arrays).
- `RWGC_Elementor_Frontend::get_element_settings()` falls back to raw element settings when Atomic resolves countries to empty/null but a legacy value remains.
- `RWGC_Surface_Settings` preserves ISO codes from nested Atomic envelopes and chip-shaped `{value,label}` rows without discarding them as `"Array"`.

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php`
- `includes/integrations/elementor/class-rwgc-elementor-frontend.php`
- `includes/targeting/class-rwgc-surface-settings.php`
- `tests/Targeting/RWGCSurfaceSettingsAtomicCountriesTest.php`
- `tests/Elementor/RWGCElementorFrontendAtomicCountriesFallbackTest.php`

## What was not changed

- Popup close / force-show behavior (`f42a920`)
- Classic Advanced-tab country SELECT2 controls
- Open known issues covered by PRs #1–#48

## Validation

- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCSurfaceSettingsAtomicCountriesTest.php` → OK (4 tests)
- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Elementor/RWGCElementorFrontendAtomicCountriesFallbackTest.php` → OK (3 tests)
- `php -l` on the three PHP production files → no syntax errors
