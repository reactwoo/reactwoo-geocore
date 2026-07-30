# Cursor output — critical bug hunt (v1.8.119 / c6fd7d3)

## Status

**done** — one NEW critical: Gutenberg post-level portable meta wipe on failed sanitize.

## Finding

`RWGC_Gutenberg_Post_Geo::sanitize_portable()` returned `''` when non-empty JSON sanitized to no usable rules (Pro-only conditions with Pro inactive, empty `rules`, schema missing). Block-editor REST saves then permanently cleared `_rwgc_post_portable_targeting`.

Distinct from open PRs #17/#18 (visibility-rule library repository/admin save only).

## Fix

Preserve non-empty submitted JSON when sanitize yields no usable set. Explicit empty string still clears. Added `tests/Targeting/RWGCGutenbergPostGeoSanitizeTest.php`.

## Files changed

- `includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php`
- `tests/Targeting/RWGCGutenbergPostGeoSanitizeTest.php`

## Not changed

Known open-PR issues (#25 recursion, #26 popups, #28/#33 assistant, #31/#37 fail-open, #35/#42 Elementor wipe, #40 preview, #41/#43 Insights, #45 routing SWITCHER, LiteSpeed/IP spoofing, rule-tester below-bar).

## Validation

`vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCGutenbergPostGeoSanitizeTest.php` → OK (4 tests, 11 assertions).
