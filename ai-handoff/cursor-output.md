# Cursor output

## Status

**done** — Explicit visibility-rules OFF now beats leftover Elementor library/portable payloads (critical hunt 2026-08-11).

## Files changed

- `includes/targeting/class-rwgc-surface-settings.php` — stamp `_rwgc_visibility_rules_explicit_off` when the modern enable key was present and not yes (after portable-flag promotion)
- `includes/targeting/class-rwgc-targeting-surface-evaluator.php` — `is_visibility_rules_enabled()` returns false on explicit OFF before `has_resolved_portable_config()`
- `includes/integrations/elementor/class-rwgc-elementor-popups.php` — popup page-settings visibility gate uses the shared evaluator/normalize path
- `tests/Targeting/RWGCTargetingSurfaceEvaluatorTest.php` — regression coverage for Atomic/classic OFF, legacy payload-only, and use_portable promotion

## Root cause

`is_visibility_rules_enabled()` fell through to leftover `rwgc_visibility_rule_library` / portable JSON after the enable switch was turned off. Classic Elementor and Atomic keep those values when the switch is hidden/unchecked.

## What was not changed

- Empty country list + `hide_if` match-all semantics (intentional / documented)
- Open PR topics #1–#53 (Atomic hide_if schema default, settings wipe, page-version spoof, etc.)
- Plugin version / release tag

## Validation

- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCTargetingSurfaceEvaluatorTest.php tests/Targeting/RWGCSurfaceSettingsCountriesTest.php` → OK (8 tests)
