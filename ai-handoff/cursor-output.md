# Cursor output — critical bug investigation

## Status

**done** — fixed high-confidence Elementor visibility assignment data-loss regression.

## Bug and impact

Opening an Elementor panel for a saved visibility-rule assignment whose rule is now marked incompatible with the current document context could clear `rwgc_applied_visibility_rule_id` automatically. If the editor session was saved after this panel rebuild, the previously assigned Geo Core visibility rule could be silently removed from that element/container, causing targeted content to render without its intended geo rule.

## Root cause

`assets/js/rwgc-elementor-library-bridge.js` rebuilt the library select with incompatible options disabled, then cleared both the select and hidden applied-rule ID when the current saved rule was incompatible. The same destructive clear path ran if an incompatible row reached the change handler.

## Fix

- Preserve the current selected rule during select rebuilds, even when compatibility metadata says it is incompatible.
- Disable incompatible choices only when they are not the existing current selection.
- In the incompatible change-handler path, revert to the existing hidden applied-rule ID instead of clearing it.
- Keep explicit user clearing (`!id`) as the only path that clears the hidden applied-rule ID.

## Files changed

- `assets/js/rwgc-elementor-library-bridge.js` — non-destructive incompatible-rule handling.
- `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` — static regression guard for preserving incompatible existing selections.

## What was not changed

- No broad Elementor control refactor.
- No changes to compatibility evaluation semantics.
- No changes to frontend runtime rule evaluation.

## Commands run and results

```bash
sudo apt-get update && sudo apt-get install -y php-cli php-xml php-mbstring composer
# exit 0

composer install --no-interaction --prefer-dist
# exit 0

vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Admin/RWGCTargetingAssistantUiRegressionTest.php
# exit 2; new test initially had a PHP string interpolation assertion bug.
# The same full file also has unrelated pre-existing static expectation failures.

php -l tests/Admin/RWGCTargetingAssistantUiRegressionTest.php
# No syntax errors detected

vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr --filter test_elementor_library_bridge_preserves_incompatible_existing_rule tests/Admin/RWGCTargetingAssistantUiRegressionTest.php
# OK (1 test, 11 assertions)

node --check assets/js/rwgc-elementor-library-bridge.js
# exit 0
```

## Remaining errors

- The full `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` file still has unrelated static expectation failures in existing assistant/popup tests. The focused new regression test passes.
