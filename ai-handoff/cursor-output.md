# Cursor output

## Status
done

## Task
Make the Elementor stale-rule warning attach on Elementor 3 and 4 after the panel exists, keep a deleted or unpublished rule selected, and list only published rules in the normal select. Includes the PR #73 signature guard.

## Files changed
- `assets/js/rwgc-elementor-library-bridge.js` — panel hooks and observer attach from `elementor:init` / `preview:loaded`, not at script load. Stale ids stay selected. Normal options are published rules only. The unchanged-signature guard from PR #73 stays.
- `includes/targeting/class-rwgc-rule-registry.php` — picker rows carry post status.
- `includes/integrations/elementor/class-rwgc-elementor-options.php` — Elementor select options omit non-published rules.
- `includes/integrations/elementor/class-rwgc-elementor-elements.php` — same filter on the fallback picker, plus unpublished/deleted option labels.
- `tests/test-rwgc-elementor-rule-status.js` — panel is created after the script loads.
- `composer.json`, `.github/workflows/test.yml` — `test:elementor-rule-status`.

## What was not changed
- Front-end evaluation, stored references (still not auto-cleared), plugin version, CHANGELOG, and tags.
- PR #73's branch was not updated. This branch starts from its head `f3f16fa`.

## Commands run
- `node tests/test-rwgc-elementor-rule-status.js` — passed.
- `node tests/test-rwgc-country-hydrate.js` — passed.
- `vendor/bin/phpunit -c phpunit.xml.dist` via `composer test` — Tests: 97, Assertions: 366, Errors: 9, Failures: 7.
- Every other `composer test:*` script — exit 0.

## Remaining errors
Pre-existing only:
- 9 errors: `RWGC_ContextAttributionTest` “headers already sent”
- 7 failures: `RWGCTargetingAssistantUiRegressionTest`

Nothing beyond that baseline.
