# Cursor output

## Status

**done** — Atomic library-only visibility mode inversion fix (critical bug hunt 2026-08-10).

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php` — remove baked-in `show_if` schema defaults for country/rules mode props
- `includes/targeting/class-rwgc-surface-settings.php` — for library-only attachments (no inline portable JSON), adopt saved library rule mode
- `tests/Targeting/RWGCSurfaceSettingsAtomicLibraryModeTest.php` — new coverage
- `tests/Targeting/RWGCTargetingSurfaceEvaluatorTest.php` — unset surface mode + hide_if match render checks

## Root cause

Atomic schema defaulted `rwgc_visibility_rules_mode` to `show_if`. The evaluator treats a non-empty surface mode as explicit and never falls through to the library rule’s `hide_if`. Classic Elementor JS bridge syncs mode from the library; Atomic only mirrored the library id.

## What was not changed

- Classic Elementor path with inline portable JSON (surface mode preserved)
- Empty-countries fail-open product rule
- Open PR families #1–#52 (disabled-toggle stale payload / IP header trust left as residual notes)

## Commands run and results

- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCSurfaceSettingsAtomicLibraryModeTest.php` → OK (3 tests)
- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCTargetingSurfaceEvaluatorTest.php` → OK (5 tests)
- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCSurfaceSettingsCountriesTest.php` → OK (3 tests)
