# Current task

**Operator production bind** — PLAN.md code/Local stop-ships are closed.

Do not enable production Cloud commerce (`REACTWOO_CLOUD_BRIDGE_ENABLED`). Do not skip PLAN.md §19 step 14.

## Done (2026-08-20)

- Gate D live Local loop
- Live Local WooCommerce Subscriptions E2E (`scripts/live_local_woo_e2e.php`)
- WCS ISO-8601 start_date conversion (`RWCC_Scheduled_Subscription::woo_start_date`)
- PLAN.md §20 shipped conservative defaults
- Figma §16 visual desktop screens (Cloud Dashboard Button + Inter)

## Remaining (operators)

1. `react-license/migrations/add_reactwoo_decision_cloud_package.sql` on the license DB
2. Store `bind_production_cloud_catalogue.sql` (meta/prices only; not Local)
3. Then — and only then — PLAN.md §19 step 14

## Do not

- Skip to PLAN.md §19 step 14
- Run `bind_production_cloud_catalogue.sql` against Local
- Unhook Elementor add-ons
