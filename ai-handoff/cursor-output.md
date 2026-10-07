# Cursor output

## Status
done

## Task
Editor warning when a block or element references a deleted, missing, draft, or trashed visibility library rule. No front-end behaviour change and no version bump (1.8.169 is on main).

## Files changed
- `includes/class-rwgc-visibility-rule-editor-status.php` — one `get_posts` lookup for published, draft, trashed, deleted, nonexistent, and other unpublished IDs. Builds the show-if / hide-if / page-variant warning. Does not delete the stored reference.
- `includes/class-rwgc-plugin.php` — load the class and register the editor ajax action.
- `includes/integrations/elementor/class-rwgc-elementor-elements.php` — put statuses for the open document and the library picker on the existing Elementor editor payload.
- `assets/js/rwgc-elementor-library-bridge.js` — inline warning under the Geo panel rule control (widgets, sections, containers, popups, and page settings, including page-variant rules). Keeps the stale option selected. Clear is a button.
- `includes/class-rwgc-gutenberg.php` — same payload for the block editor.
- `blocks/geo-content/index.js` — inspector warning, replacement select, and clear for `visibilityRuleLibrary` / `appliedVisibilityRuleId`.
- `admin/views/visibility-rules-list.php` — short note that editors keep the reference and show a warning.
- `tests/test-rwgc-visibility-rule-status.php`, `composer.json`, `.github/workflows/test.yml` — CLI coverage wired into `test:all`.

## What was not changed
- Front-end evaluation (`get_rule_set()`, `is_rule_active_for_frontend()`, show-if / hide-if / `variant_rule_inactive`).
- Stored references are not removed unless the editor clicks Clear rule or picks another rule.
- Plugin version, CHANGELOG version, and tags.

## Commands run
- `php tests/test-rwgc-visibility-rule-status.php` — all assertions passed.
- `vendor/bin/phpunit -c phpunit.xml.dist` — Tests: 97, Assertions: 366, Errors: 9, Failures: 7.
- Every other `composer test:*` script in `test:all` — exit 0.

## Remaining errors
Pre-existing only:
- 9 errors: `RWGC_ContextAttributionTest` “headers already sent”
- 7 failures: `RWGCTargetingAssistantUiRegressionTest`

Nothing beyond that baseline. CI skips `cursor/*` branches, so this is the local run.
