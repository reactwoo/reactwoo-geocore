# Decision Cloud — Commerce, upgrade and onboarding

Status: Sprint 6 implemented in Decision Cloud `0.15.0` (billing reconciliation and last-known entitlement resilience). Sprint 4 remains in `0.14.0`, Sprint 5 in `0.13.0`, Sprint 3 in `0.12.0`, Sprint 2 in `reactwoo-api-manager` (`includes/cloud-commerce/`), and Sprint 1 in Cloud `0.11.0`. **Production identity cutover** is tracked in Decision Cloud `docs/identity-production-cutover.md` (Cloud `0.17.3` live as of 2026-08-19).  
**Canonical commercial model:** [PLAN.md](./PLAN.md) (2026-08-19). Sprints 1–6 are store/Cloud plumbing. They do **not** implement bundle supersession, upgrade credit display, or downgrade selection. Do not enable production Cloud commerce until PLAN.md stop-ship conditions pass.  
Architecture: separate SaaS control plane; shared ReactWoo.com commerce.  
Figma: [Reactwoo — Cloud Dashboard](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=275-2). Activation / upgrade / site-connection screens: [node 310:1796](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=310-1796). Required upgrade/downgrade frames: page **09 Decision Cloud commerce** in the same file ([PLAN.md §16](./PLAN.md)).

This is neither a second billing system inside Decision Cloud nor a reskinned WooCommerce My Account screen.

## Product offers

Superseded 2026-08-19. Do **not** treat Decision Cloud and covered individual plugins as two ongoing bills.

- **Level 1 — Individual plugins** — Geo Core Pro, Geo Commerce, Geo Optimise (and other eligible SKUs). Local premium for that product only. No Decision Cloud access.
- **Level 2 — Decision Cloud bundle** — upgrade tier. Replaces billing for plugins **covered by the selected Cloud plan**. Same plugin binaries; Cloud grant supplies included premium capabilities, downloads, and updates.
- **Upgrade** — remaining-term credit on ReactWoo.com; covered subscriptions become superseded/non-renewing **only after** Cloud activation succeeds; local configuration is retained. Connection and `management_mode` do not change as a billing side-effect.
- **Downgrade** — customer selects which individual plugins to resume (or none) at Cloud end. No automatic restart or charge without consent.

Coverage matrix, credit, states, tests, and stop-ship: [PLAN.md](./PLAN.md).

## Delivery

| Sprint | Owner | Status |
|--------|-------|--------|
| 1 Commerce boundary | Decision Cloud | **Done** (`0.11.0`, tagged) |
| 2 Store companion bridge | ReactWoo.com (`reactwoo-api-manager` / `includes/cloud-commerce/`) | **Done** |
| 3 Identity and activation | Decision Cloud | **Done** (`0.12.0`) |
| 5 Standalone upgrade | Decision Cloud | **Done** (`0.13.0`) |
| 4 Guided site connection | Decision Cloud | **Done** (`0.14.0`) |
| 6 Billing resilience | Decision Cloud | **Done** (`0.15.0`) |
| 7 Bundle commerce | Store + Geo Core + Decision Cloud | **In progress** — coverage maps, grant OR, supersession after activation, comma-separated variation mapping for the inspected single variable product (parent 3166). Stop-ship until PLAN.md remaining items pass. |
| Production identity cutover | Store + Decision Cloud | **In progress** (2026-08-19) — Cloud `0.17.3` live; owner `--apply` and Sign in unverified. See Decision Cloud `docs/identity-production-cutover.md` |

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
- Checkout handoff uses `rw_action=upgrade` on ReactWoo.com. **Sprint 5 credit is incomplete:** PLAN.md requires a visible, auditable remaining-term credit and atomic supersession of covered subscriptions. Opaque `applied_at_checkout` is not sufficient.
- Portal screens match Figma 23–25 copy; already-subscribed workspaces skip to the complete state
- Upgrade complete CTA navigates to `#/sites`

## Sprint 4 acceptance

- Portal-authenticated `POST /pairing-tokens` works without `ALLOW_DEV_PAIRING` when `organisation_id` is present
- Pairing codes display as `RW-XXXX-XXXX`, expire in 10 minutes, and `POST /sites/pair` accepts dashed, undashed, and en-dash forms
- Pairing never flips `management_mode`; Observe only leaves `local`; Cloud managed is an explicit portal `POST .../management-mode`
- Portal screens match Figma 26–31 copy; capability import is a local selection only (no WordPress deletes)
- Public pairing JSON has no `site_secret`, licence keys, Stripe, or Paystack

## Sprint 6 acceptance

- Portal-authenticated reconciliation calls only the allowlisted ReactWoo.com reconcile endpoint with the shared `RWCC_RECONCILE_TOKEN`
- Store outages keep the last valid entitlement snapshot; paid capabilities are not revoked because reconciliation failed
- A store `404` reports `subscription_not_found` and does not cancel the local subscription
- `GET /organisations/:orgId/billing` remains local-only; reconciliation is an explicit portal `POST`
- Portal states distinguish active, payment grace, paused, and last-known entitlement conditions with payment-method handoff
- Public billing and reconcile JSON contains no Stripe, Paystack, customer, subscription, or site-secret identifiers

## Sprint 7 acceptance (in progress)

See [PLAN.md](./PLAN.md). Implemented so far: coverage maps, remaining-term credit calculation **and checkout display/block**, post-activation supersession, overlap detection **and admin correction with a non-refund credit quote**, Geo Core grant OR, product-bound handoff signatures, Local catalogue bind + read-only validator (parent 3166 / 3172–3177), **downgrade selection** on My Account (confirm + pending Woo subscriptions at Cloud end, including none), entitlement handover **wired into My Account downloads**, conservative licence reuse **on provision/token**, Decision Cloud product copy, licence Cloud zip grants, portal included-SKU billing view.

Still required before production:

- Covered individual subscriptions detected before Cloud checkout
- Remaining-term credit calculated, displayed, and capped
- Cloud activation atomically supersedes covered subscriptions
- Activation failure leaves individuals unchanged
- Downgrade selection schedules individuals at Cloud end (or none)
- No double billing; Geo Core grant OR without destructive exclusive Cloud provider
- Checkout handoff signatures valid
- Staging E2E WooCommerce tests pass

Woo status/credit mechanic choice (§20), **production** settings/meta/price binding, finished Figma visual design, and staging E2E are not done. Do not enable production Cloud commerce.
