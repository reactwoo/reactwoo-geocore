# Cursor output

## Status
done

## Task
Production enablement after operator SQL + Cloud flag (PLAN.md §19 step 14).

## Files changed
- API Manager: `RWCC_Settings::merge_empty` / `catalogue_gaps`, admin warning, `scripts/merge_production_cloud_settings.php`, tests, docs
- Geo Core: PLAN.md §13/§14/§21, work-packages, commerce-and-onboarding, gate-d, current-task
- Decision Cloud: `docs/identity-production-cutover.md`

## What was not changed
- Production database (operators already ran SQL)
- No `git push origin` of API Manager (production SSH)
- No paid checkout
- No private-window Sign in
- Geo Core plugin version **1.8.163** (PLAN production enablement)

## Commands run
- Public smoke: Store API 3166/3172–3177 purchasable at PLAN GBP; product copy present; license package 2271; Decision Cloud health 0.17.9
- `php tests/run.php` — all passed (including merge_empty)

## Remaining
1. Paste or merge `rwcc_settings` product IDs if empty
2. Deploy API Manager 2.1.13+ if production is still on 2.1.12 (ISO dates)
3. Private-window Sign in
4. Paid production checkout E2E
5. Gate E
