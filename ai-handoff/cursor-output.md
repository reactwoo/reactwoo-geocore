# Cursor output

## Status
needs-review

## Task
Gate D request-time Decision Runtime (cached manifest → Experience Slot variant). Live Local page load still required.

## Files changed
- `includes/decision/class-rwgc-request-decision.php` — provider for `reactwoo_current_decision_result`
- `includes/decision/class-rwgc-decision.php` — load request decision
- `includes/cloud/class-rwgc-cloud.php` — init request decision
- `includes/contracts/class-rwgc-contract-condition.php` — `op`/`type` aliases
- `includes/slots/class-rwgc-experience-slot-registry.php` — `put_runtime()`
- `tests/test-rwgc-request-decision.php` — cache evaluate, `op`/`in`, no Cloud HTTP
- Version **1.8.160** (header, `RWGC_VERSION`, readme, CHANGELOG)
- `docs/architecture/gate-d.md`, `work-packages.md`, `PLAN.md` §21 note

## What was not changed
- Production `REACTWOO_CLOUD_BRIDGE_ENABLED`
- PLAN.md §19 step 14
- Elementor editor load / widget unhooking
- WooCommerce staging E2E / §20 decisions

## Commands run
- `php tests/test-rwgc-request-decision.php`
- `php tests/test-rwgc-contracts.php`
- `composer test:request-decision` (via php)

## Remaining
Live Local: `npm start` Decision Cloud → pair → author GB audience + `rw_*` slot_id → publish → sync → load frontend → stop Node → reload still shows variant.
