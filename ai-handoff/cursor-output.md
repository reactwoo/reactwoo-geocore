# Cursor output — critical bug hunt 2026-07-30

## Status

**done** — New critical: Gutenberg post portable targeting wipe. Fixed on `cursor/critical-bug-investigation-0834` (`7b5abe7`), PR #46.

## Bug and impact

Saving a page/post in the block editor could permanently clear `_rwgc_post_portable_targeting` when submitted portable JSON sanitized to no usable rules (e.g. Pro-only campaign/weather conditions while GeoCore Pro is inactive, or empty `rules`). Post-level geo targeting was lost with no error; restricted content could then render without the intended rules.

## Root cause

`RWGC_Gutenberg_Post_Geo::sanitize_portable()` (REST/meta `sanitize_callback`) returned `''` whenever `RWGC_Targeting_Rule_Set_Schema::sanitize()` returned null. Block-editor REST saves re-submit registered meta, so a failed sanitize wiped stored targeting. Same wipe pattern as visibility-rule library (#17/#18), but those PRs do not cover this Gutenberg surface.

## Fix

Preserve non-empty submitted JSON when sanitize yields no usable set. Explicit empty string still clears.

## Files changed

- `includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php`
- `tests/Targeting/RWGCGutenbergPostGeoSanitizeTest.php` (new)
- `ai-handoff/cursor-output.md`

## What was not changed

- Visibility-rule CPT/repository sanitize (#17/#18)
- Elementor library bridge wipe (#35/#42)
- Other open criticals (#26, #28, #31/#37, #33, #40, #41, #43, #45, etc.)

## Validation

- Focused PHPUnit: `tests/Targeting/RWGCGutenbergPostGeoSanitizeTest.php`
- Second-pass hunt after #46: no additional NEW critical beyond open PRs #1–#46

## Remaining known criticals (already open)

Highest remaining unmerged: #25, #26, #28, #31/#37, #33, #35/#42, #40, #41, #43, #44, #45, #17/#18, #21.
