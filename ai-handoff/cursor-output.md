# Cursor output — Rule Tester frontend preview isolation

## Status

**done**

## Bug and root cause

- Signed Rule Tester previews apply the simulated country before `template_redirect`.
- `RWGC_Routing` did not recognize this preview mode, so routed pages could redirect away from the page selected for testing.
- `RWGC_Cache_Compat` read the same simulated country during `send_headers` and could persist it in `rwgc_cc` for 24 hours, affecting the manager's normal browsing after preview exit.

## Files changed and why

- `includes/class-rwgc-routing.php` — bypass page routing when a verified Rule Tester frontend preview is active.
- `includes/integrations/class-rwgc-cache-compat.php` — do not persist the simulated preview country to the full-page-cache vary cookie.
- `tests/Targeting/RWGCRuleTesterFrontendPreviewIsolationTest.php` — regression coverage for routing and cookie isolation.
- `ai-handoff/cursor-output.md` — current implementation handoff.

## What was not changed

- Token signing, expiry, user binding, and preview permissions.
- Normal visitor routing and cache-cookie behavior.
- Rule evaluation, Page Version routing, or unrelated Rule Tester results.

## Commands run and results

- Read-only commit and caller-chain inspection: completed.
- Cross-repository Optimise integration contract check: completed; the v1.8.118 embedded `RWGA_*` compatibility classes are intentional and not a bug.
- `php -l includes/class-rwgc-routing.php` — passed.
- `php -l includes/integrations/class-rwgc-cache-compat.php` — passed.
- `php -l tests/Targeting/RWGCRuleTesterFrontendPreviewIsolationTest.php` — passed.
- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCRuleTesterFrontendPreviewIsolationTest.php` — passed (2 tests, 2 assertions).

## Remaining errors

- None.
