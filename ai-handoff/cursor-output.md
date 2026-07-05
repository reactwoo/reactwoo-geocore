# Cursor output — Critical bug investigation

## Status

**done** — fixed one high-confidence Elementor editor data-loss bug.

## Bug and impact

`assets/js/rwgc-elementor-library-bridge.js` rebuilt the Elementor visibility-rule library select with incompatible rules disabled. If the current saved rule was now considered incompatible for the natural document context, the rebuild path selected the placeholder and immediately wrote an empty `rwgc_applied_visibility_rule_id`.

Concrete trigger:

1. A page/variant has an Elementor section using a saved visibility rule whose portable JSON is scoped to a different context (for example product page type).
2. The user opens that page in Elementor after v1.8.109+.
3. The library bridge marks the existing rule incompatible and clears the hidden applied-rule ID during panel setup.
4. Saving the page persists the cleared assignment, removing the visibility rule from the element.

## Root cause

Compatibility metadata was used as a destructive editor mutation instead of a warning. The bridge treated "incompatible for this editor context" as "must clear saved assignment" even when that assignment already existed.

## Files changed

| File | Why |
|------|-----|
| `assets/js/rwgc-elementor-library-bridge.js` | Preserve the current incompatible option on rebuild, keep it selectable for display, and show the compatibility warning without clearing the saved applied-rule ID. Explicit user selection of the placeholder can still clear the rule. |
| `tests/Admin/RWGCElementorLibraryBridgeRegressionTest.php` | Static regression coverage for preserving current incompatible assignments and preventing the incompatible guard from writing an empty applied-rule ID. |

## What was not changed

- Rule evaluator semantics.
- Rule compatibility status calculation.
- Rule Tester preview/token behavior.
- Geo Commerce / Optimise / Geo AI / Pro code.
- Existing unrelated `RWGCTargetingAssistantUiRegressionTest` expectations.

## Commands run and results

```bash
sudo apt-get update && sudo apt-get install -y php-cli php-xml php-mbstring composer
composer install --no-interaction --prefer-dist
vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Admin/RWGCElementorLibraryBridgeRegressionTest.php
# OK (2 tests, 15 assertions)

vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Admin
# Failed: 7 existing RWGCTargetingAssistantUiRegressionTest static expectation failures unrelated to this bridge file.

php -l tests/Admin/RWGCElementorLibraryBridgeRegressionTest.php
# No syntax errors detected
```

## Remaining errors

- `tests/Admin` has unrelated failures in `RWGCTargetingAssistantUiRegressionTest` around targeting-assistant static UI expectations (`popupTargetHint`, resolver helper names, etc.). The new bridge regression test passes in isolation.
