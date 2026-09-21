# Cursor output

## Status
done

## Task
Complete returning / new visitor conditions in the Geo family.

## Files changed
- Geo Core attribution: first-seen `rwgc_returning` cookie, request memo, UTM no longer marks the same request as returning
- `RWGC_Rule_Evaluator` resolvers for `returning_visitor`, `new_visitor`, `visitor.returning`
- Free schema types so sanitize does not strip them when Pro is off
- Platform capability `visitor.returning` + Decision Runtime lazy context
- Tests, changelog, version **1.8.164**
- Decision Cloud portal capability schema `visitor.returning` (**0.17.10**)

## What was not changed
- Geo Commerce UI already listed the conditions; it now evaluates via the Core adapter
- Geo Core Pro still advertises `visitor.returning` and skips if Core owns it
- Cloud commerce operator steps / Gate E

## Commands run
- `php tests/test-rwgc-rule-evaluator.php` — passed
- `php tests/test-rwgc-returning-visitor.php` — passed
- `php tests/test-rwgc-contracts.php` — passed
- `php tests/test-rwgc-request-decision.php` — passed
- `php tests/test-rwgc-platform-capabilities.php` — passed
- Decision Cloud `portal.test.js`, `manifest-compile.test.js`, `health.test.js` — passed
- Local `vendor/bin/phpunit` still missing `PHPUnit\TextUI\Command` (known Windows vendor issue)

## Remaining errors
None for returning visitors.
