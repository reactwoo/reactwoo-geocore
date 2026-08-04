# Cursor output

## Status

**done** — Atomic country targeting fail-open fix for Geo Core **v1.8.126**.

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-frontend.php` — merge raw geo settings when Atomic resolve drops countries / nulls
- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php` — `egp_countries` schema is union of `string-array` (chips) + legacy `string`
- `tests/Targeting/RWGCSurfaceSettingsCountriesTest.php` — normalize/parse coverage for legacy string + chips
- `reactwoo-geocore.php`, `readme.txt` — **1.8.126**

## Root cause

After 1.8.124 chips (`string-array`), saved Atomic countries still typed as `string` failed `Props_Resolver` (`$$type` mismatch → `null`). Empty country list fail-opens → France-gated content rendered for UK.

## What was not changed

- Empty country list = allow all (product rule)
- Popup close behaviour (1.8.125)
- Page routing / MaxMind detection

## Remaining (manual)

- Hard-refresh a page with France-only Atomic Flex/Div content while simulating UK — FR block must stay hidden
- Re-select France via chips and save once if an older document still shows blank countries in the editor
