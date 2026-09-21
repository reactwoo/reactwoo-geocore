# Cursor output

## Status
done

## Task
Fix the same-visit returning-visitor flip and the skipped Geo Core tests check on Cursor Cloud PRs.

## Files changed
- `includes/context/class-rwgc-context-attribution.php` — pin new vs returning for the browsing session (`rwgc_rv`)
- Tests for the second HTTP request in the first visit, and for the next session
- `.github/workflows/test.yml` — `cursor/*` PRs complete the `test` job instead of skipping it
- Version **1.8.165**, changelog, schema help text

## What was not changed
- Persistent `rwgc_returning` / `rwgc_ft` still mark a later session as returning
- Decision Cloud (already live at 0.17.11)
- Cloud commerce operator steps / Gate E

## Commands run
- `php tests/test-rwgc-returning-visitor.php`
- `php tests/test-rwgc-rule-evaluator.php`

## Remaining errors
None for this fix.
