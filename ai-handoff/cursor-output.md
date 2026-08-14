# Cursor output

## Status
done

## Task
WP20 — AI recommendations (advisory only; no autonomous live-site changes).

## Files changed

### Decision Cloud (`0.10.0`)
- `recommendations` store + generate/approve/dismiss
- PII stripped from datasets
- Approve creates **draft** experience/variant and does **not** compile
- Manifest compiler excludes non-live experiences
- Portal Recommendations nav
- Tests: `tests/recommendations.test.js`

### Geo Core (`1.8.156`)
- `RWGC_Contract_Recommendation`
- `RWGC_Cloud_Recommendations` cache + admin approve/dismiss
- Sync refresh on maintenance
- Tests: `composer test:recommendations`

## What was not changed
- Visitor render path (still no Cloud HTTP)
- Autonomous optimisation (explicitly out of scope)
- Gate D / Gate E still need a live site

## Commands run
- Decision Cloud `npm test`
- Geo Core recommendations, contracts, cloud-connector, decision-runtime

## Remaining
- Gate D / Gate E live-site validation
