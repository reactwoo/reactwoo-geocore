# Cursor output

## Status
done

## Task
WP19 — Performance pass. Profile Decision Runtime; zero Cloud HTTP on visitor render; lazy context; candidate-only evaluation.

## Files changed

### Geo Core (`reactwoo-geocore` 1.8.155)
- Candidate experiences first; evaluate only referenced audiences; per-request audience match cache
- Condition AND/OR short-circuit
- Lazy `RWGC_Contract_Context` resolvers + `RWGC_Decision_Context_Factory` / `RWGC_Context_Value_Cache`
- Manifest parse memo per revision
- Cloud HTTP forbidden after `template_redirect` (not merely `!is_admin()`, so pairing tests/admin still work)
- Docs: `docs/performance/decision-runtime.md`
- Tests: `composer test:decision-perf`

### Decision Cloud
- Not changed

## What was not changed
- Decision Cloud version / tags
- WP20 AI
- Gate D / Gate E still need a live site

## Commands run
- `php tests/test-rwgc-decision-perf.php`
- `php tests/test-rwgc-decision-runtime.php`
- `php tests/test-rwgc-cloud-connector.php`
- `php tests/test-rwgc-decision-parity.php`

## Remaining
- WP20 AI recommendations
