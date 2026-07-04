# Cursor output — critical bug investigation

## Status

**needs-review** (implementation pass pushed; validation in progress)

## Summary

Found and fixed critical admin rule tester/editor regressions introduced around v1.8.109.

## Files changed

- `admin/js/rwgc-visibility-rule-preview.js` — restored the missing `bindTextareaRefresh()` wrapper and removed the dead `bindTestPanel()` call so the visibility rule editor preview script parses and refreshes.
- `includes/class-rwgc-visibility-rule-tester.php` — normal pages/posts now infer `page`/`post` instead of `other`; applied previews can evaluate inline portable JSON when no library rule ID exists.
- `includes/class-rwgc-elementor-assignment-discovery.php` — Elementor inline portable assignments without a saved library rule ID are now returned to the applied-target tester.
- `admin/js/rwgc-visibility-rule-tester.js` — applied-target mode can run inline portable assignments and sends their JSON to the preview endpoint.
- `tests/Targeting/RWGCVisibilityRuleTesterRegressionTest.php` — regression coverage for page/post inference and inline Elementor assignment discovery.

## What was not changed

- No release/version bump or tag.
- No broad refactor of targeting evaluator precedence; library rule assignments still evaluate the saved library rule.
- No changes to satellite plugin code.

## Commands run

Validation pending after commit/push per automation workflow.

## Remaining errors

None known yet; validation is next.
