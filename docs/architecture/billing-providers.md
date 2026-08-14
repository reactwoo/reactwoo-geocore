# Cloud commerce

Canonical for Decision Cloud billing. Parent plan: [reactwoo-cloud-v1-architecture-and-delivery-plan.md](./reactwoo-cloud-v1-architecture-and-delivery-plan.md). Onboarding sequence: [commerce-and-onboarding.md](./commerce-and-onboarding.md).

**Implemented (Sprint 1):** Decision Cloud `0.11.0`. WooCommerce on ReactWoo.com is the only commercial source of truth. Decision Cloud has no Stripe, Paystack or PayGate adapters.

**Implemented (Sprint 3):** First purchase provisions a workspace from `rw_cloud_provisioning_id`. `/activate` exchanges a hashed, single-use claim.

## Ownership

| Concern | System of record |
|---------|------------------|
| Products, prices, coupons, tax, invoices, refunds | ReactWoo.com WooCommerce |
| Subscription and renewal state | WooCommerce Subscriptions |
| Checkout and payment methods | ReactWoo.com (hosted checkout / My Account) |
| Organisations, entitlements, sites | Decision Cloud |

Decision Cloud stores a **snapshot**: internal plan, status, renewal, grace, `cloud.*` keys. It never stores gateway names, payment tokens or WooCommerce consumer credentials.

## Internal plans

`starter` | `growth` | `scale` map from WooCommerce product/variation IDs:

| Internal plan | Env |
|---------------|-----|
| starter | `WOOCOMMERCE_PRODUCT_STARTER` |
| growth | `WOOCOMMERCE_PRODUCT_GROWTH` |
| scale | `WOOCOMMERCE_PRODUCT_SCALE` |

Entitlement keys stay `cloud.personalisation`, `cloud.commerce`, `cloud.optimise`, `cloud.components`, `cloud.insights`, `cloud.recommendations`, `sites.max`, `team_members.max`, `history.days`.

## Subscription row (generic)

```json
{
  "provider": "woocommerce",
  "status": "active",
  "plan": "growth",
  "customer_id": "(WooCommerce customer, never returned on public APIs)",
  "subscription_id": "(WooCommerce subscription)",
  "processor_plan_id": "(product or variation ID)",
  "current_period_end": "2026-09-14T12:00:00",
  "cancel_at_period_end": false,
  "grace_until": null
}
```

## Checkout and account

Portal **Subscribe** and **Manage billing** create signed ReactWoo.com handoff URLs (`REACTWOO_STORE_ORIGIN` + HMAC `REACTWOO_HANDOFF_SECRET`). Redirects are restricted to that origin. Payment-gateway hosts are rejected.

## Webhooks

| Path | Auth |
|------|------|
| `POST /api/v1/billing/webhooks/woocommerce` | `X-WC-Webhook-Signature` HMAC-SHA256 (base64), idempotent on `X-WC-Webhook-Delivery-ID` |

Status map: `active` / `pending-cancel` → active; `on-hold` / `pending` / `failed` → past_due (grace); `cancelled` / `expired` / deleted topic → canceled; `order.refunded` → past_due unless already canceled. Configuration is never deleted on lapse. Payloads may include `rwcc.timestamp` + `rwcc.replay_window_sec`; stale timestamps are ignored.

## WordPress

Unchanged: Core caches the entitlement snapshot from heartbeat. `RWGC_Entitlements::allows()` does not know how the org was billed. No commerce HTTP on the visitor render path.

## Sprint 2 store companion

Implemented in `reactwoo-api-manager/includes/cloud-commerce/`. Details: that plugin’s `docs/cloud-commerce-bridge.md`.

## Out of scope in Decision Cloud

- Direct payment-gateway adapters
- Custom card forms
- WooCommerce REST credentials in the browser
- Sprint 2 store companion (activation claims, checkout meta) — **done** in `reactwoo-api-manager`
