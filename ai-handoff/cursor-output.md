# Cursor output

## Status
done

## Task
Critical-bug hunt after Geo Core **1.8.164** (returning visitors).

## Files changed
- `includes/context/class-rwgc-context-attribution.php` — pin new vs returning for the browsing session (`rwgc_rv`) so the next page/AJAX request does not flip a first visit to returning
- Tests for the second HTTP request in the first visit, and for the next session
- Changelog (Unreleased) + schema help text

## What was not changed
- Persistent `rwgc_returning` / `rwgc_ft` still mark a later session as returning
- Product version stays **1.8.164** (fix is Unreleased until tagged)
- Cache vary, entitlements, Store API listing geo (#67), and other previously filed PRs

## Commands run
- `php tests/test-rwgc-returning-visitor.php` — passed
- `php tests/test-rwgc-rule-evaluator.php` — passed
- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Engine/RWGC_ContextAttributionTest.php` — OK (9 tests)

## Remaining errors
None for this fix.
