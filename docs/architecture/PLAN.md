# ReactWoo commercial and subscription model

**Status:** Canonical commercial plan. Local/code complete. Operator catalogue SQL, license package SQL, and production `REACTWOO_CLOUD_BRIDGE_ENABLED` are done (verified 2026-08-21). Remaining: paste/merge `rwcc_settings` product IDs if still empty, production checkout E2E, identity Sign in, then Gate E.  
**Authority:** This document supersedes any previous wording that Decision Cloud and covered individual-plugin subscriptions should normally run as separately billed subscriptions.  
**Last updated:** 2026-08-21  
**Parent architecture:** [reactwoo-cloud-v1-architecture-and-delivery-plan.md](./reactwoo-cloud-v1-architecture-and-delivery-plan.md)  
**Store/onboarding sequence:** [commerce-and-onboarding.md](./commerce-and-onboarding.md)  
**Billing ownership:** [billing-providers.md](./billing-providers.md)  
**Entitlement contract:** [../contracts/entitlements.md](../contracts/entitlements.md)

This is the commercial contract. Implementation follows §19. It does not change live WooCommerce products, production flags, or licences until those steps are complete.

---

## 1. Confirmed commercial model

ReactWoo has a **progressive subscription model**:

1. Customers may begin with one or more **individual plugin** subscriptions.
2. **Decision Cloud** is the **upgrade bundle** for the ReactWoo plugin suite.
3. When a customer upgrades to Decision Cloud, the Cloud bundle **replaces the billing** for all individual plugin subscriptions **covered by that bundle**.
4. Customers must **not** continue paying separately for included individual plugins while also paying for Decision Cloud.
5. If the customer later leaves Decision Cloud, they **choose** which individual plugins they want to continue using.
6. Only the **selected** individual plugin subscriptions begin when the Cloud entitlement ends.
7. If they select **no** individual products, they return to **free/core** functionality when their paid Cloud period ends.

Cloud is a **bundle entitlement**. It does not represent permanent ownership of each individual plugin.

### What this model is not

- It is **not** two ongoing bills for the same covered capability (Cloud + Geo Core Pro, Cloud + Geo Commerce, Cloud + Geo Optimise).
- It is **not** a global technical switch that deletes local WordPress configuration when Cloud is purchased.
- It is **not** Decision Cloud creating fake permanent individual subscriptions so that plugins appear independently licensed.
- Source-tracked overlapping **grants** exist for **transition safety**, not as a licence to double-charge.

---

## 2. Product levels

### Level 1 — Individual plugins

Customers may subscribe separately to:

| Product | Catalog / licence slug | Premium capability key |
|---------|------------------------|------------------------|
| Geo Core Pro | `reactwoo-geocore-pro` | `cloud.personalisation` |
| Geo Commerce | `reactwoo-geo-commerce` | `cloud.commerce` |
| Geo Optimise | `reactwoo-geo-optimise` | `cloud.optimise` |
| Other eligible suite plugins | See mapping below | Plan-specific |

Each individual subscription grants **only** that product’s premium capabilities, downloads, and updates.

Individual plugin customers **do not** receive Decision Cloud console access, site connection for Cloud authoring, Cloud analytics, or Cloud team features.

Geo Core (`reactwoo-geocore`) remains **free** at every level. It is never superseded because it is never billed.

### Level 2 — Decision Cloud bundle

Decision Cloud is the upgrade tier **above** the individual products.

An active Decision Cloud subscription grants:

- Decision Cloud console access
- All plugin capabilities included in the **selected Cloud plan**
- Plugin downloads and updates for **included** products
- Site connection and Cloud authoring
- Cloud analytics, teams, and other plan-specific features

The same plugin binaries are used. Premium runtime access for covered products comes from the **Cloud bundle grant**, not from a continuing individual renewal.

---

## 3. Covered product mapping

WooCommerce product/variation IDs stay in ReactWoo.com settings (`product_starter` / `product_growth` / `product_scale` as **comma-separated variation IDs**, plus individual plugin product IDs). Feature code must continue to see only internal plan ids and capability keys. Runtime code must **not** hard-code production IDs.

Internal Cloud plans today: `starter` | `growth` | `scale` (see Decision Cloud `src/services/plans.js`).

Do **not** replace the Decision Cloud catalogue with three parent products. Decision Cloud is **one** customer-facing variable subscription.

Do **not** build a monolithic Cloud plugin. The same production packages remain: Geo Core (connection/evaluation), Geo Core Pro, Geo Commerce, Geo Optimise. Decision Cloud is the control plane for organisations, sites, members, audiences, experiences, insights, recommendations, plan entitlements, and connected-site configuration. WooCommerce remains the source of truth for products, prices, orders, subscriptions, renewals, refunds, billing status, and customer ownership.

### 3.0 Inspected production catalogue (ReactWoo.com, 2026-08-19)

Public Store API `GET /wp-json/wc/store/v1/products/3166` (`https://reactwoo.com/product/reactwoo-decision-cloud/`). IDs were **not** inferred from display names. Local ReactWoo WP (`127.0.0.1:10011`) now contains this catalogue after the 2026-08-19 production restore; Local bind is **not** a production publish.

| Role | WooCommerce ID | SKU / notes |
|------|----------------|-------------|
| Parent (`variable-subscription`, slug `reactwoo-decision-cloud`) | **3166** | Bind as `product_decision_cloud` / `WOOCOMMERCE_PRODUCT_PARENT` only. **Not** a plan. |
| Starter monthly | **3172** | SKU `RW-DC-STARTER-M` |
| Starter annual | **3173** | Plan attribute Starter, Billing Annual |
| Growth monthly | **3174** | SKU `RW-DC-GROWTH-M` |
| Growth annual | **3175** | |
| Scale monthly | **3176** | SKU `RW-DC-SCALE-M` |
| Scale annual | **3177** | |

Store API listed prices as £0 and the product was not purchasable at first inspection (2026-08-19). Re-checked 2026-08-21 after operator SQL: parent **3166** is purchasable; variations **3172–3177** sell at PLAN marketing GBP (£39 / £390 / £99 / £990 / £249 / £2,490). **Never** identify a plan from price, title, SKU text, or variation description.

**Recommended public prices** (marketing / plan-display only; WooCommerce charges the live amount):

| Plan | Monthly | Annual (~two months free) | Positioning |
|------|---------|---------------------------|-------------|
| Starter | £39 | £390 | 1 site, 2 members, 14-day history. Geo Core Pro + personalisation + components + insights + recommendations. **Does not** include Geo Commerce or Geo Optimise. |
| Growth | £99 | £990 | **Most popular.** 5 sites, 10 members, 90-day history. Full current geo suite. |
| Scale | £249 | £2,490 | 25 sites, 50 members, 365-day history. Full geo suite. Priority support only if the business is prepared to provide it. |

**Variation metadata** (set on each variation, not derived from names):

| Key | Values |
|-----|--------|
| `_rw_cloud_plan` | `starter` \| `growth` \| `scale` |
| `_rw_cloud_billing_cycle` | `monthly` \| `annual` |
| `_rw_cloud_product_type` | `decision_cloud` (parent and/or variations) |

Settings fallback: `product_starter=3172,3173` (monthly then annual). Multiple variation IDs per internal plan are required. Sandbox IDs must use a separate settings/env map.

**Downloads:** Mark the product/variations Virtual. Do **not** attach Geo Core Pro / Geo Commerce / Geo Optimise ZIPs to every variation. My Account lists entitled packages dynamically via API Manager store-download, e.g. `Geo Core Pro — Included with Decision Cloud`. No new installer plugin for the initial implementation. Downloadable may be enabled if Woo requires it, without static satellite files.

**Live product (2026-08-21):** prices and `_rw_cloud_*` meta are bound; PLAN product copy (`rwcc-cloud-product-copy`) is on the public product page (Cloud bridge loaded). Bind `rwcc_settings` product IDs if still empty (`product_starter=3172,3173` etc.; individuals Geo Core Pro **2294**, Geo Commerce **2893**, Geo Optimise **2891**). Merge-only helper: `reactwoo-api-manager/scripts/merge_production_cloud_settings.php`. Do not attach satellite ZIPs to variations.

### 3.1 Cloud plan matrix

| Cloud plan | Included plugin SKUs | Included capability keys | `sites.max` | `team_members.max` | `history.days` | Not included in this plan |
|------------|----------------------|--------------------------|-------------|--------------------|----------------|---------------------------|
| **starter** | `reactwoo-geocore-pro` | `cloud.personalisation`, `cloud.components`, `cloud.insights`, `cloud.recommendations` | 1 | 2 | 14 | `reactwoo-geo-commerce`, `reactwoo-geo-optimise`, and all products in §3.2 |
| **growth** | `reactwoo-geocore-pro`, `reactwoo-geo-commerce`, `reactwoo-geo-optimise` | All of starter, plus `cloud.commerce`, `cloud.optimise` | 5 | 10 | 90 | Products in §3.2 |
| **scale** | Same as growth | Same as growth | 25 | 50 | 365 | Products in §3.2 |

`cloud.components` in this matrix means Cloud **component-variant** authoring for included experiences, not ownership of ReactWoo Atomic Pro.

Only products **included in the selected Cloud plan** are superseded.

A product outside the Cloud bundle **may** continue as a separate subscription. Checkout **must** explain why it remains separately billed (it is not covered by the selected plan).

### 3.2 Products not covered by Decision Cloud (default)

These remain separately billed unless a later plan revision explicitly adds them:

| Product | Slug | Reason |
|---------|------|--------|
| Geo Core (free) | `reactwoo-geocore` | Never billed |
| ReactWoo Reviews | `reactwoo-reviews` | Separate product line |
| ReactWoo Atomic (free) | `reactwoo-atomic` | Never billed |
| ReactWoo Atomic Pro | `reactwoo-atomic-pro` | Not in starter/growth/scale coverage |
| LinkedIn / Social Core | `reactwoo-linkedin-core` | Not in starter/growth/scale coverage |
| ReactWoo Flow | `reactwoo-flow` | Internal operations; not a customer Cloud SKU |
| WHMCS Bridge | `reactwoo-whmcs-bridge` | Separate product line |

Legacy licence aliases (`geo-elementor` → `reactwoo-geocore-pro`, `reactwoo-geo-ai` → `reactwoo-geo-optimise`) must be treated as the **canonical** SKU when detecting covered subscriptions.

### 3.3 Mapping rules

- Coverage is evaluated **per selected Cloud plan**, not as a global “any Cloud SKU covers every plugin”.
- Upgrading to **starter** while holding Geo Commerce or Geo Optimise must **not** supersede those two; they stay separately billed with an explicit checkout explanation.
- Upgrading to **growth** or **scale** supersedes Geo Core Pro, Geo Commerce, and Geo Optimise.
- Adding a new Cloud plan later requires an update to this table **before** that plan can be sold.

---

## 4. Upgrade: individual products to Decision Cloud

The upgrade journey must work as follows:

1. Customer chooses **Upgrade to Decision Cloud**.
2. ReactWoo.com identifies all **active** individual subscriptions belonging to that customer.
3. The store determines which subscriptions are **covered** by the selected Cloud plan.
4. The customer sees an upgrade summary showing:
   - Current individual subscriptions
   - Which products will be included in Cloud
   - Which individual renewals will stop
   - Any products **not** covered by Cloud
   - Remaining-term credit
   - New Cloud price
   - Effective date
5. The remaining paid value of eligible individual subscriptions is calculated **server-side** and applied as an upgrade credit.
6. The customer **explicitly confirms** the upgrade.
7. The Cloud subscription is created and **successfully activated**.
8. **Only after** Cloud activation succeeds are covered individual subscriptions marked as superseded / non-renewing.
9. Covered individual subscriptions must **not renew again**.
10. Cloud entitlement becomes the **active commercial source** for the included plugin capabilities.
11. Existing plugin configuration, licence history, and content are **retained**.
12. There must be **no gap** in premium access and **no double billing**.

Do **not** cancel or deactivate individual entitlements before Cloud activation succeeds.

### If Cloud activation fails

- Individual subscriptions remain active.
- Individual renewal schedules remain unchanged.
- No entitlement is removed.
- Any incomplete Cloud order is recoverable or refundable.

---

## 5. Upgrade credit

Credit is calculated by **ReactWoo.com / WooCommerce**, not by Decision Cloud.

### Policy

- Credit is based on the **unused paid period** of eligible individual subscriptions.
- Eligibility: active, paid, not trial, not refunded, not already superseded, not expired, not cancelled.
- Previously cancelled, refunded, expired, or trial subscriptions do **not** create credit.
- Multiple individual subscriptions may contribute to **one** Cloud upgrade credit.
- Only subscriptions **covered by the selected Cloud plan** contribute.
- The customer sees the calculation **before** confirming.
- Calculations are **auditable** (inputs, formula, result, currency, tax treatment, timestamp, actor).
- Credit **cannot exceed** the permitted Cloud checkout value (the payable Cloud order / first invoice after tax rules).
- Currency and tax treatment remain controlled by WooCommerce.

### Suggested calculation (implementation must match WooCommerce tax settings)

For each eligible subscription:

```text
unused_fraction = remaining_paid_seconds / current_period_seconds
line_credit     = unused_fraction × amount_paid_for_current_period (ex-tax or inc-tax per store policy)
```

Then:

```text
gross_credit = sum(line_credit of covered eligible subscriptions)
applied_credit = min(gross_credit, permitted_cloud_checkout_value)
```

Unused credit above the Cloud checkout cap is **not** paid out as cash unless a later refund policy says so. Record the cap in the audit.

---

## 6. Subscription relationships

Store-side relationship / transition records (WooCommerce subscription meta or a dedicated store table). Decision Cloud stores **snapshots**, not the commercial relationship.

| Field | Purpose |
|-------|---------|
| `superseded_by_subscription_id` | Cloud subscription that replaced this individual subscription |
| `superseded_at` | When supersession was committed |
| `superseded_reason` | `cloud_upgrade` (required value for this journey) |
| `original_subscription_id` | Individual subscription being left |
| `replacement_subscription_id` | Cloud or later individual subscription |
| `transition_effective_at` | When commercial source actually changes |
| `transition_status` | `pending` \| `activating` \| `committed` \| `failed` \| `rolled_back` \| `scheduled` |
| `covered_product_ids` | Woo product/variation IDs included in this transition |
| `credit_amount` | Applied upgrade credit |
| `credit_currency` | ISO currency |

Do **not** permanently delete the original individual subscriptions. They remain available for:

- Audit history
- Invoices
- Previous licence records
- Support
- Potential downgrade selection
- Migration rollback

Cloud must **not** create fake permanent individual subscriptions.

Idempotency: product IDs, subscription IDs, order IDs, and webhook delivery IDs must be linked so retries cannot create duplicate subscriptions, credits, or transitions.

---

## 7. Entitlement model

Continue using **source-tracked entitlement grants**. That supports **transition safety**, not intentional double billing.

A capability may **temporarily** have both:

- an individual subscription grant (`source: standalone` / `individual_subscription`)
- a Cloud bundle grant (`source: cloud`)

This overlap is permitted **only** during:

- Upgrade activation
- Grace periods
- Migration
- Rollback
- Legacy-account correction

Once Cloud activation is confirmed, included individual subscriptions become **non-renewing / superseded** and Cloud becomes the **commercial source**.

### Effective access

```text
effective_access(capability) =
    any currently valid grant for that capability
```

Each grant must retain its **source** and **validity dates** so one entitlement source can end without accidentally removing another valid source.

The **billing** system must additionally prevent customers from being **actively charged twice** for the same covered capability.

### Geo Core resolution

`RWGC_Composite_Entitlement_Provider` resolves **grants**, not a single exclusive provider: `allows(key)` is true if any currently valid grant for `key` is allowed. A connected Cloud snapshot must not destroy a still-valid individual/standalone grant. Canceled Cloud snapshots are not commercially active.

Required behaviour:

- Resolve **grants**, not a single exclusive provider.
- `allows(key)` is true if **any** currently valid grant for `key` is allowed.
- Limits (`sites.max`, `team_members.max`, `history.days`) use the **commercial source of record** when Cloud is the active bundle; during overlap, use the **more generous currently valid limit** only if that does not extend unpaid Cloud-only features after Cloud has ended.
- Ending the Cloud grant must not revoke a still-valid individual grant for a product the customer kept or never had superseded.

---

## 8. Downgrade: Decision Cloud to individual products

Decision Cloud cancellation **must** include a downgrade journey.

The customer must be offered:

- Continue with Geo Core Pro
- Continue with Geo Commerce
- Continue with Geo Optimise
- Continue with any other eligible individual plugin
- Select **multiple** individual plugins
- Continue with **no** paid plugins

The screen must show:

- Current Cloud renewal / end date
- Cloud features that will end
- Individual plugin options
- Price for each selected plugin
- Combined price
- Sites covered
- Effective billing date
- Whether existing configuration is supported by the selected products
- Any Cloud-only resources that will become read-only or inactive

The customer must **explicitly confirm** any new individual subscriptions.

Do **not** automatically restart or charge old subscriptions without consent.

---

## 9. Downgrade timing

Preferred behaviour:

1. Customer requests Cloud cancellation or downgrade.
2. Cloud remains active until the **paid-through date**.
3. Customer selects the individual plugins they want to retain.
4. Selected individual subscriptions are **scheduled to begin** at the end of the Cloud period.
5. Cloud grants remain active until the transition time.
6. Individual grants activate **atomically** at the transition time.
7. Cloud grants end.
8. No overlapping charge is created.
9. No entitlement gap occurs for selected products.
10. Unselected premium capabilities end.

### No individual plugins selected

- Cloud access continues until the paid-through date.
- At expiry, premium Cloud and plugin bundle grants end.
- Geo Core free functionality remains.
- Existing content and configuration are retained.
- Cloud-authored resources become read-only / inactive according to the retention policy.
- **No** new subscription is created.

---

## 10. Cancellation and failed payment

Distinguish **voluntary downgrade** from **failed payment**.

### Voluntary cancellation

- Offer downgrade selection immediately.
- Keep Cloud active through the paid period.
- Schedule selected individual subscriptions for the Cloud end date.

### Failed payment

- Apply the configured Cloud grace period.
- Prompt the customer to **repair billing**.
- Also allow them to choose individual plugins as a **fallback**.
- Do **not** automatically charge for fallback products without consent.
- If no payment or fallback selection is completed, Cloud bundle access ends after grace.

---

## 11. Immediate downgrade

If an immediate downgrade is supported:

- Show the effective date clearly.
- Calculate any credit or refund through WooCommerce.
- **Activate selected individual products before** Cloud grants are removed.
- Require explicit customer confirmation.
- Record the complete transition audit.

Immediate downgrade is optional. End-of-term scheduled downgrade is the **preferred** default.

---

## 12. Licence and plugin behaviour

### When upgrading to Cloud

- Existing plugin installations remain.
- Existing settings and content remain.
- Historical individual licence keys remain recorded.
- Covered individual subscriptions stop renewing.
- Premium runtime access is provided by the Cloud bundle grant.
- Plugin downloads and updates for covered products are provided through the Cloud subscription.

### When downgrading

- Reuse a previous product licence **only if** it is technically safe **and** the customer has explicitly selected that product.
- Otherwise create a new individual subscription / licence **linked** to the previous product history.
- Do not require the customer to reinstall the plugin.
- Update the entitlement source without deleting configuration.
- Selected plugin capabilities continue without interruption.
- Unselected capabilities end when Cloud ends.

---

## 13. Decision Cloud and WordPress coexistence

Do **not** reintroduce a global “local versus Cloud” technical switch.

While subscribed to Cloud, customers may still use **local WordPress configuration** and **Cloud-authored configuration** together.

The commercial upgrade replaces **individual billing**. It must not replace or delete local configuration.

These are separate concepts:

| Concept | Meaning |
|---------|---------|
| Commercial state | Individual products **or** Cloud bundle (for covered SKUs) |
| Connection state | Connected or disconnected |
| Resource origin | Local or Cloud |
| Runtime | Local execution through Geo Core |
| Billing authority | ReactWoo.com WooCommerce |
| Management mode | Observe / local vs Cloud-managed authoring — an explicit site choice, not a billing side-effect |

Pairing a site, buying Cloud, and switching `management_mode` must remain **independent** operations.

---

## 14. Required account states

| ID | State | Commercial meaning |
|----|--------|--------------------|
| 1 | Free / core only | Geo Core free; no paid individual or Cloud subscription |
| 2 | One individual plugin | Exactly one Level 1 subscription |
| 3 | Multiple individual plugins | Two or more Level 1 subscriptions; no Cloud bundle |
| 4 | Cloud upgrade pending | Cloud order/activation in progress; individuals still renewing |
| 5 | Cloud active | Cloud is the commercial source for covered SKUs; covered individuals are superseded / non-renewing |
| 6 | Cloud active with legacy overlapping individual billing requiring correction | Cloud is active **and** covered individuals are still renewing — **invalid**; must be flagged and corrected |
| 7 | Cloud cancellation scheduled | Cloud paid-through date set; still entitled until then |
| 8 | Cloud downgrade selection pending | Cancellation started; customer has not finished product selection |
| 9 | Individual subscriptions scheduled to start | Selected Level 1 subs exist with start = Cloud end |
| 10 | Downgrade completed | Cloud grants ended; selected individual grants active |
| 11 | Cloud failed-payment grace | Repair billing and/or choose fallback products |
| 12 | Cloud expired with no fallback products | Premium Cloud and bundle plugin grants ended; Geo Core free remains |
| 13 | Cloud reactivation | Customer resumes Cloud before or after a scheduled downgrade; must cancel scheduled individuals or supersede them again without double charge |

State 6 is a **defect state**, not a sellable plan.

---

## 15. Double-billing protection

- Before Cloud checkout, detect covered **active** individual subscriptions.
- Do **not** allow an unexplained full-price Cloud purchase while covered individual subscriptions continue renewing.
- Show and resolve **all** covered subscriptions during checkout.
- Cloud activation must mark covered subscriptions as superseded / non-renewing.
- Reconciliation must detect accidental overlap (Cloud active + covered individual still renewing).
- Account administration must flag double-billed customers (state 6).
- Corrective credit / refund workflows must exist and be documented for operators.
- Product and subscription IDs must be idempotently linked.
- Webhook retries must not create duplicate subscriptions, credits, or transitions.

---

## 16. Figma and UX flows

Existing Cloud screens: [Reactwoo — Cloud Dashboard](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=275-2) and activation / upgrade / site-connection [node 310:1796](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=310-1796). Those screens are **not** sufficient for this commercial model.

Commerce copy frames (wireframes, not finished visual design): page **09 Decision Cloud commerce** — [node 327:2](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=327-2).

Required designs (store My Account and/or Decision Cloud portal, as appropriate):

1. Individual plugin account overview
2. “Upgrade to Decision Cloud” entry point
3. Existing subscriptions detected
4. Cloud coverage comparison
5. Upgrade-credit breakdown
6. Products that will stop renewing
7. Upgrade confirmation
8. Cloud activation success
9. Cloud billing view showing **Included in Decision Cloud**
10. Cancel or downgrade Cloud
11. Select individual plugins to retain
12. Downgrade price summary
13. Effective-date confirmation
14. Scheduled downgrade state
15. Downgrade completed
16. Cloud expired with no selected plugins
17. Failed-payment recovery and fallback selection
18. Administrative double-billing warning

Within My Account, covered products must display:

> Included in your Decision Cloud subscription

They must **not** appear as separately renewable subscriptions.

---

## 17. Required test matrix

- One individual product upgraded to Cloud
- Multiple individual products upgraded to Cloud
- Remaining-term credit across multiple subscriptions
- Cloud activation failure preserves individual subscriptions
- Successful Cloud activation stops covered renewals
- Non-covered products continue separately (for example starter Cloud + Geo Commerce)
- No duplicate billing
- No entitlement gap during upgrade
- Voluntary downgrade to one plugin
- Voluntary downgrade to multiple plugins
- Downgrade to no paid plugins
- Failed Cloud payment with fallback selection
- Failed Cloud payment without fallback selection
- Scheduled downgrade cancellation
- Cloud reactivation before downgrade date
- Webhook retries
- Subscription reconciliation
- Existing plugin configuration after upgrade and downgrade
- Downloads and updates during each transition
- Tax / currency calculations
- Administrative correction of legacy double billing

---

## 18. Stop-ship conditions

Cloud commerce must **not** be enabled until:

- Covered individual subscriptions can be detected reliably.
- Upgrade credit is calculated and displayed.
- Cloud activation can atomically supersede covered subscriptions.
- Double billing is prevented.
- Downgrade selection exists.
- Selected individual subscriptions can be scheduled for the Cloud end date.
- Entitlement handover has no access gap.
- Checkout handoff signature defects are fixed.
- Geo Core no longer uses Cloud as a destructive exclusive entitlement provider.
- End-to-end WooCommerce tests pass on Local (`scripts/live_local_woo_e2e.php`). Production checkout E2E is an operator paid-path run now that catalogue SQL and the production flag are on.

Sprints 1–6 in [commerce-and-onboarding.md](./commerce-and-onboarding.md) built store handoff, activation, and snapshot billing. They **do not** satisfy this list.

---

## 19. Implementation order

| Step | Status |
|------|--------|
| 1. Covered SKU and capability mappings | **Done** in Decision Cloud `plans.js` and store `RWCC_Coverage`. Inspected production variation IDs are recorded in §3.0. Runtime binding remains settings/env (`product_starter=3172,3173` etc.) — **not** PHP/JS defaults. Variation meta/prices are on production (2026-08-21). Paste or merge `rwcc_settings` if those keys are still empty. |
| 2. Source-tracked entitlement grants | **Done** in Geo Core composite provider (OR of standalone + commercially valid Cloud). |
| 3. Subscription relationship / transition records | **Done** (`RWCC_Transition`). Persisted as subscription meta; not a replacement for Woo subscription history. |
| 4. Signed checkout handoff | **Done**. New URLs bind `add-to-cart` in HMAC. Store verifies six-field or legacy five-field, then rejects plan/product mismatches. |
| 5. Upgrade eligibility and credit calculation | **Done.** Remaining-term credit is calculated, capped at the Cloud cart **line total excluding tax**, displayed on cart/checkout, and unexplained full-price Cloud checkout is blocked (`RWCC_Checkout_Credit`). Shipped mechanic is a non-taxable negative cart fee (§20.2). Mismatched currencies are ineligible (`currency_mismatch`) with no silent FX. Decision Cloud reports `licence_credit: applied_at_checkout` and `credit_owner: store`. |
| 6. Atomic Cloud activation and supersession | **Done.** After a successful Cloud activation webhook, covered individuals are marked `_rwcc_superseded`, next automatic payment is cleared, and renewals are blocked. Native Woo status is not changed to `pending-cancel` or `on-hold` (§20.1). |
| 7. Reconciliation and double-billing detection | **Done** for detection and operator correction. `RWCC_Overlap` detects state 6; reconcile snapshots include `billing_overlap`. Admin **Cloud overlap** screen can inspect a Cloud subscription and, with explicit confirm, stop overlapping covered renewals without deleting history. `quote_credit()` records remaining-term amounts with `refund: false` / `requires_finance: true`. Automatic refund is not built (§20.5). |
| 8. Downgrade selection and scheduled subscription creation | **Done.** My Account form lets the customer keep Geo Core Pro / Commerce / Optimise, multiple, or none; confirmation is required; records schedule start = Cloud paid-through date with `charge_now=false`. Confirmed rows are materialized as pending Woo subscriptions via `RWCC_Scheduled_Subscription` (`wcs_create_subscription` when WCS is present). ISO-8601 dates are converted to MySQL UTC for WCS. Reactivation cancels saved schedules **and** created pending individuals. Immediate refunds remain none-automatic (§20.5). |
| 9. Geo Core entitlement resolution | **Done.** |
| 10. My Account and Decision Cloud UX | **Done** for shipped copy. My Account shows included-in-Cloud, downgrade selection, and product-page copy for Decision Cloud. Portal billing lists included SKUs and hands cancel/downgrade to ReactWoo.com. |
| 11. Figma flows | **Done** for visual screens. Page **09 Decision Cloud commerce** ([327:2](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=327-2)) keeps the 18 copy wireframes and adds **Visual — PLAN §16 desktop** using Cloud Dashboard Button components and Inter. |
| 12. End-to-end tests and CI | **Done** for Local. Unit/smoke tests cover coverage, credit, handover, licence reuse, overlap quote, currency mismatch, scheduled subs, superseded ZIP hiding, Cloud on-hold downloads. Store e2e fixture includes the §17 handover/overlap matrix. Live Local WooCommerce Subscriptions E2E (no payment): `reactwoo-api-manager/scripts/live_local_woo_e2e.php` passed 2026-08-20 (catalogue bind, credit quote, supersession, pending materialize, cancel). Production reactwoo.com checkout E2E is the remaining paid-path operator run. |
| 13. Staging migrations | **Done.** Local validate OK 2026-08-20. Operator production SQL run 2026-08-21: store `bind_production_cloud_catalogue.sql` (parent 3166, variations 3172–3177 purchasable at PLAN prices) and license `add_reactwoo_decision_cloud_package.sql` (`packages.slug` `reactwoo-decision-cloud`, id **2271**, `is_active=1`). |
| 14. Production feature flags | **Done** 2026-08-21. `REACTWOO_CLOUD_BRIDGE_ENABLED` is on: public product page renders `rwcc-cloud-product-copy`. Decision Cloud `https://decision.reactwoo.com/health` is `0.17.9`. Sign-in URL remains `https://reactwoo.com/my-account/?rwcc_open_cloud=1`. |

Do not treat the production flag as a substitute for filled `rwcc_settings` product IDs or a paid checkout E2E.

---

## 20. Shipped WooCommerce subscription-transition decisions

These were open during implementation. The following are the **shipped conservative defaults** as of 2026-08-20. Finance may later replace refunds or the credit fee without changing coverage/supersession semantics.

1. **Supersession mechanism** — custom non-renewing: `_rwcc_superseded` meta plus `next_payment = 0`. Native Woo status is left unchanged so history stays queryable. Not a native switch, `pending-cancel`, or `on-hold`.
2. **Credit application mechanic** — non-taxable negative cart fee (`RWCC_Checkout_Credit`). Auditable via `_rwcc_upgrade_credit` / `_rwcc_upgrade_credit_audit`. Not a coupon, wallet, or native switch proration.
3. **Permitted Cloud checkout value** — first Cloud cart **line total excluding tax** (`line_total`). Credit is capped at that amount; unused credit is not paid out as cash.
4. **Scheduled individual start** — pending WooCommerce Subscription (`wcs_create_subscription`, `charge_now=false`) with start = Cloud paid-through. ISO-8601 is converted to MySQL UTC (`RWCC_Scheduled_Subscription::woo_start_date`). Action Scheduler is not used.
5. **Immediate downgrade refunds** — none automatic. Overlap quotes remaining-term amounts with `refund: false` / `requires_finance: true`. Unused Cloud time is not refunded by software.
6. **Multi-currency** — mismatched currencies are ineligible (`currency_mismatch`). No silent FX conversion.
7. **Licence reuse vs mint** — Cloud key is never an individual key. After Cloud ends, reuse a historical key for the same domain+slug only if that plugin was selected; otherwise mint later or none. License DB has package slug `reactwoo-decision-cloud` (id **2271**, verified 2026-08-21 via `GET /api/packages`).
8. **Live product ID catalogue** — inspected 2026-08-19 (parent 3166, variations 3172–3177). Production meta/prices bound 2026-08-21; product is purchasable at PLAN GBP. Do not treat the parent ID as a plan.
9. **Starter vs Commerce/Optimise** — starter does **not** cover Commerce or Optimise. Store checkout copy matches.
10. **Atomic Pro / Reviews / LinkedIn** — remain outside Cloud unless a later mapping revision includes them.

---

## 21. Remaining implementation

| Area | Remaining |
|------|-----------|
| Store companion | Production flag on; catalogue meta/prices live. If `rwcc_settings` product IDs are still empty, paste them in wp-admin or run `merge_production_cloud_settings.php` (empty keys only; secrets untouched). Paid production checkout E2E remains an operator run. ISO start-date conversion for pending individuals is in local API Manager `2.1.13` (`132e7fe`) — deploy that to ReactWoo.com if production is still on `2.1.12`. |
| Decision Cloud | Portal billing lists included SKUs and store downgrade handoff (`rw_action=downgrade`). Upgrade JSON credits the store (`credit_owner: store`). Charging stays on the store. Production `0.17.9`. Identity Sign in (private window) still unverified. |
| Geo Core | Done for grant OR. Gate D live Local loop passed 2026-08-20. Next architecture gate: **Gate E**. |
| Licence / updates | **Done** for the package row. `reactwoo-decision-cloud` is active on license.reactwoo.com (id 2271). Code grants covered plugin slugs and passes `plan_code`. |
| Figma | §16 copy frames plus visual desktop screens on [09 Decision Cloud commerce](https://www.figma.com/design/BZFmgpDMSm0OMtnC19lNQ4/Reactwoo?node-id=327-2). |

---

## 22. Previous statements replaced by this document

| Previous wording | Replacement |
|------------------|---------------|
| Decision Cloud and covered individual plugins **normally remain separately billed** | Cloud **replaces** billing for covered individual plugins |
| Source-tracked overlapping grants as a **sellable additive** commercial state | Overlap is **temporary transition safety** only; billing must not double-charge |
| “Standalone plugin” and “Decision Cloud subscription” as two ongoing parallel offers | Level 1 individuals **or** Level 2 Cloud bundle for covered SKUs |
| Sprint 5 “licence credit and switch” as the finished upgrade | Credit plus **atomic supersession**, coverage mapping, and no double renewals |
| Geo Core: Cloud snapshot **replaces** standalone as soon as Cloud is connected | Grants **OR** for access; Cloud is the commercial source after successful activation; connection ≠ billing |
| Cloud as permanent ownership of each included plugin | Cloud is a **bundle entitlement** that ends; downgrade selection required |
| Opaque `applied_at_checkout` credit | Visible, auditable remaining-term credit with a cap |
| Automatic restart of old subscriptions on Cloud cancel | Explicit customer selection; schedule at Cloud end; no charge without consent |
