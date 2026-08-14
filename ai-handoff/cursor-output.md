# Cursor output

## Status
done

## Task
WP15 — Stripe + Cloud entitlements. Releases for Elementor editor-load fixes shipped first.

## Files changed

### Releases
- Geo Core `v1.8.150`, WHMCS Bridge `v1.1.5.4`, Atomic `free-v0.7.1` + `pro-v0.7.1` pushed to GitHub.

### Decision Cloud (`reactwoo-decision-cloud` 0.5.0)
- `EntitlementService` maps plans → `cloud.*` keys (no Stripe IDs on the public snapshot)
- Signed, idempotent, replay-safe Stripe webhooks
- Hosted Checkout + Customer Portal
- Grace on `invoice.payment_failed`; canceled plans lock keys without deleting org/site/audience rows
- Heartbeat returns entitlements for WP cache
- Portal Billing page

### Geo Core
- `RWGC_Entitlements` facade → Standalone | Cloud providers
- Heartbeat caches entitlements; disconnect clears the cache
- `tests/test-rwgc-entitlements.php`

## What was not changed
- Visitor render path still has no Cloud HTTP
- Satellite license classes still exist; new feature code should use the facade
- WP16 migration not started
- Gate D / Gate E still need a live site

## Commands run
- Decision Cloud `npm test`
- Geo Core `php tests/test-rwgc-entitlements.php`

## Remaining
- Configure live Stripe keys on the Decision Cloud host
- Gate D end-to-end publish → WP
