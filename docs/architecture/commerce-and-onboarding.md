# Decision Cloud — Commerce, upgrade and onboarding

Status: Sprint 4 implemented in Decision Cloud `0.14.0` (guided WordPress pairing, Figma 26–31). Sprint 5 remains in `0.13.0`. Sprint 3 activation remains in `0.12.0`. Sprint 2 remains in `reactwoo-api-manager` (`includes/cloud-commerce/`). Sprint 1 commerce boundary remains in Cloud `0.11.0`.  
Architecture: separate SaaS control plane; shared ReactWoo.com commerce.  
Figma: [Reactwoo — Cloud Dashboard](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=275-2). Activation / upgrade / site-connection screens: [node 310:1796](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=310-1796).

This is neither a second billing system inside Decision Cloud nor a reskinned WooCommerce My Account screen.

## Product offers

- **Standalone plugin** — purchased on ReactWoo.com, local mode, contextual upgrade to Cloud.
- **Decision Cloud subscription** — purchased on ReactWoo.com, provisions an organisation, same plugin binaries with Cloud entitlements.
- **Existing-customer upgrade** — licence credit and switch on ReactWoo.com; local rules stay until an explicit Cloud management switch.

## Delivery

| Sprint | Owner | Status |
|--------|-------|--------|
| 1 Commerce boundary | Decision Cloud | **Done** (`0.11.0`, tagged) |
| 2 Store companion bridge | ReactWoo.com (`reactwoo-api-manager` / `includes/cloud-commerce/`) | **Done** |
| 3 Identity and activation | Decision Cloud | **Done** (`0.12.0`) |
| 5 Standalone upgrade | Decision Cloud | **Done** (`0.13.0`) |
| 4 Guided site connection | Decision Cloud | **Done** (`0.14.0`) |
| 6 Billing resilience | Decision Cloud | Later |

Store companion: [`reactwoo-api-manager/docs/cloud-commerce-bridge.md`](../../../reactwoo-api-manager/docs/cloud-commerce-bridge.md).

## Sprint 1 acceptance

- No Stripe, Paystack or PayGate integration code in Decision Cloud `src/`
- Checkout and account actions hand off to `REACTWOO_STORE_ORIGIN`
- WooCommerce webhook HMAC + delivery-id idempotency
- Public billing JSON is Cloud-safe (plan, status, renewal, grace, entitlements, handoff flags)
- Last valid entitlement snapshot remains during store outage / grace

Commerce contract: [billing-providers.md](./billing-providers.md).

## Sprint 3 acceptance

- First Cloud purchase with `rw_cloud_provisioning_id` and no existing org creates one workspace
- The same provisioning id never mints a second organisation
- `GET /activate` is a dedicated split-screen (Figma 20–22), not the portal shell
- `GET /api/v1/activation/status` and `POST /api/v1/activation/claims/exchange` are public (claim is the secret)
- Valid claim attaches ReactWoo identity; used / expired / forged claims cannot create another workspace
- No-claim visits show the not-active upgrade state (Figma 22); **View Cloud upgrade** opens `/portal/#/upgrade`

## Sprint 5 acceptance

- Eligibility is derived from the Cloud subscription snapshot only (no licence keys, Woo IDs, or prices in public JSON)
- Inactive / canceled workspaces are eligible (`reason: standalone_upgrade`); active and past-due-in-grace are not (`already_subscribed`)
- `GET /api/v1/organisations/:orgId/upgrade` and `POST .../upgrade/checkout` live behind portal auth
- Checkout handoff uses `rw_action=upgrade` on ReactWoo.com; licence credit is opaque (`applied_at_checkout`)
- Portal screens match Figma 23–25 copy; already-subscribed workspaces skip to the complete state
- Upgrade complete CTA navigates to `#/sites`

## Sprint 4 acceptance

- Portal-authenticated `POST /pairing-tokens` works without `ALLOW_DEV_PAIRING` when `organisation_id` is present
- Pairing codes display as `RW-XXXX-XXXX`, expire in 10 minutes, and `POST /sites/pair` accepts dashed, undashed, and en-dash forms
- Pairing never flips `management_mode`; Observe only leaves `local`; Cloud managed is an explicit portal `POST .../management-mode`
- Portal screens match Figma 26–31 copy; capability import is a local selection only (no WordPress deletes)
- Public pairing JSON has no `site_secret`, licence keys, Stripe, or Paystack
- Next: Sprint 6 billing resilience
