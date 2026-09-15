# Cursor output

## Status
done

## Task
Critical bug hunt (cron). Found visitor-path fatal + unpublished library rules still targeting.

## Files changed
- `includes/class-rwgc-visibility-rule-repository.php` — `get_rule_set()` reads meta only (breaks registry↔repository recursion)
- `includes/targeting/class-rwgc-variant-rule-applications.php` — unpublished/missing rules are not active; `is_page_variant_rule()`
- `includes/targeting/class-rwgc-targeting-surface-evaluator.php` — library inactive skips targeting; variant inactive still fail-closes
- `tests/test-rwgc-library-rule-lookup.php` — cache-miss, draft/trash, variant fail-closed
- `composer.json` — `test:library-rule-lookup`
- `ai-handoff/cursor-output.md` — this note

## What was not changed
- Plugin version / release tag
- Drafts still appear in the builder picker (admin UX only)
- Event-queue lost-update, GeoIP XFF (#62), visitor_id experiments (#63)

## Commands run
- `php tests/test-rwgc-library-rule-lookup.php` — all passed
- `php tests/test-rwgc-rule-evaluator.php` — pre-existing hide-mode fail (does not load `functions-rwgc.php`); not caused by this change

## Remaining
None for this bug.
