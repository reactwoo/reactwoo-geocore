# Current task

**PLAN.md remaining stop-ships** (Gate D is complete).

Do not enable production Cloud commerce (`REACTWOO_CLOUD_BRIDGE_ENABLED`). Do not skip PLAN.md §19 step 14.

## Done

- Gate D live Local loop (2026-08-20): author → sync → visitor sees `GATE-D-LIVE-UK` → Cloud off → same URL still shows variant. See `docs/architecture/gate-d.md`.

## Remaining (PLAN.md)

1. Staging WooCommerce E2E
2. Figma §16 visual polish (Decision Cloud commerce)
3. Operator: license `add_reactwoo_decision_cloud_package.sql`; store `bind_production_cloud_catalogue.sql` (meta/prices only; not Local)
4. §20 Woo status / credit mechanic (product/finance decisions)

## Do not

- Skip to PLAN.md §19 step 14
- Run `bind_production_cloud_catalogue.sql` against Local
- Unhook Elementor add-ons
