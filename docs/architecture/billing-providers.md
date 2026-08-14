# Cloud billing providers

Canonical for Decision Cloud billing. Parent plan: [reactwoo-cloud-v1-architecture-and-delivery-plan.md](./reactwoo-cloud-v1-architecture-and-delivery-plan.md).

**Implemented:** Decision Cloud `0.6.0` (WP15b). Stripe remains the default rail; Paystack is the Africa adapter.

## Providers (v1)

| Provider | Role | Checkout | Customer self-serve |
|----------|------|----------|---------------------|
| **Stripe** (WP15) | Default for most markets | Hosted Checkout | Stripe Customer Portal |
| **Paystack** (WP15b) | Africa-based subscriptions and customers | Paystack hosted / Popup | Paystack customer portal or manage-via-link; no custom card UI |

Do not add a third processor in v1. Do not collect card numbers in ReactWoo.

## Same product, two rails

Internal plans stay `starter` | `growth` | `scale`. Entitlement keys stay:

`cloud.personalisation`, `cloud.commerce`, `cloud.optimise`, `cloud.components`, `cloud.insights`, `sites.max`, `team_members.max`, `history.days`

Each processor maps **its** catalogue onto those plans:

| Internal plan | Stripe | Paystack |
|---------------|--------|----------|
| starter | `STRIPE_PRICE_STARTER` | `PAYSTACK_PLAN_STARTER` |
| growth | `STRIPE_PRICE_GROWTH` | `PAYSTACK_PLAN_GROWTH` |
| scale | `STRIPE_PRICE_SCALE` | `PAYSTACK_PLAN_SCALE` |

Local currency on the Paystack plans (NGN, GHS, ZAR, KES, …) is a catalogue concern. Entitlements do not change with currency.

## Organisation billing record

```json
{
  "provider": "stripe",
  "status": "active",
  "plan": "growth",
  "grace_until": null,
  "customer_id": "(processor, never returned to feature APIs)",
  "subscription_id": "(processor)",
  "processor_plan_id": "(price or plan code)"
}
```

`provider`: `stripe` | `paystack`.

**v1 constraint:** one organisation uses **one** processor at a time. First successful subscribe locks the provider. Switching Stripe ↔ Paystack is a support/migration path, not self-serve.

## Checkout routing

Portal Billing offers both processors when both are configured.

**Suggest Paystack** when the organisation billing country is in a Paystack-supported African market (examples: NG, GH, ZA, KE, CI). **Suggest Stripe** otherwise. The customer may still choose the other processor (international cards, USD invoicing, existing Stripe customer).

Do not geo-detect from the WordPress visitor. Billing country is an org setting / checkout field, not a page-render concern.

## Webhooks

| Processor | Path | Auth |
|-----------|------|------|
| Stripe | `POST /api/v1/billing/webhooks/stripe` (WP15 also accepted `/billing/webhooks`) | `Stripe-Signature` HMAC |
| Paystack | `POST /api/v1/billing/webhooks/paystack` | `x-paystack-signature` HMAC-SHA512 of raw body |

Both adapters call the same `EntitlementService.applyPlan` / `markPastDue` / `markCanceled`.

Idempotent on processor event id. Replay-safe (reject stale timestamps where the processor supplies them). Grace on payment failure. Never delete org, sites, audiences, or experiences on lapse.

Map at least:

| Lifecycle | Stripe | Paystack |
|-----------|--------|----------|
| Subscribe / paid | `checkout.session.completed`, `invoice.paid` | `charge.success`, `subscription.create` |
| Past due | `invoice.payment_failed`, `customer.subscription.updated` past_due | `invoice.payment_failed`, failed recurring charge |
| Cancel | `customer.subscription.deleted` | `subscription.disable`, `subscription.not_renew` |

## WordPress

Unchanged: Core caches the **entitlement snapshot** from heartbeat. `RWGC_Entitlements::allows()` does not know which processor billed the org. No processor HTTP on the visitor render path.

## Out of scope (v1)

- Split billing (Stripe + Paystack on one org)
- Custom card forms
- Flutterwave or other African processors
- Processor calls from WordPress plugins (`react-cloud` remains the Google vault, not billing)
