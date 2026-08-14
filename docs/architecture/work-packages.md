# ReactWoo Cloud v1 — Implementation work packages

Run **one package at a time**. Parent plan: [reactwoo-cloud-v1-architecture-and-delivery-plan.md](./reactwoo-cloud-v1-architecture-and-delivery-plan.md).

**Current active package:** Gate D (live Local site: author → publish → sync → variant; Cloud off still works). Sprint 6 billing resilience remains in Decision Cloud `0.15.0`.

---

## WORK PACKAGE 0 — Repository audit + architecture map

**Do this before changing code.**

### Cursor instruction

```text
You are working on the ReactWoo plugin suite.

Before making any code changes, audit the entire available ReactWoo codebase.

Identify:

1. ReactWoo Core / Geo Core
2. Geo Core Pro
3. Geo Commerce
4. Geo Optimise
5. Atomic / existing ReactWoo widget/component work
6. existing licensing implementation
7. existing audience/campaign/rule models
8. Elementor integrations
9. Gutenberg integrations
10. REST API endpoints
11. tracking/event systems
12. database tables/options/post types used by ReactWoo
13. shared utilities duplicated between plugins

Produce docs/architecture/current-state.md.

For each plugin document:

- bootstrap file
- namespaces
- minimum WordPress/PHP requirements
- dependencies
- database/storage
- REST endpoints
- public hooks
- internal hooks
- rule evaluation flow
- frontend rendering flow
- licensing flow
- builder integrations

Then create docs/architecture/cloud-migration-impact.md.

Do NOT refactor or implement features yet.

Important constraints:

- Existing standalone functionality must continue working.
- Existing stored rules must remain compatible.
- Existing public hooks/classes must not be renamed without a compatibility layer.
- Identify duplicated decision logic that should eventually move into Core.
- Identify anything that would make Cloud integration unsafe.

Finish with a recommended migration order and list of technical risks.
```

### Gate

Don’t proceed until we understand what’s actually there. Outputs: `current-state.md`, `cloud-migration-impact.md`.

---

## WORK PACKAGE 1 — Shared contracts

```text
Implement the ReactWoo platform contract layer.

Goal:
Define stable shared data structures for the future ReactWoo Decision Engine without changing current behaviour.

Add a versioned internal namespace for:
Capability, Context, Condition, Audience, Experience, ExperienceSlot,
Variant, Experiment, Goal, Event, Manifest, Entitlement

Use immutable/value-object patterns where practical.
Define JSON serialisation and validation boundaries.
Introduce schema version: reactwoo_schema_version = 1

Do NOT yet connect to ReactWoo Cloud.
Do NOT migrate existing rules.
Do NOT change frontend behaviour.

Create documentation docs/contracts/ including:
capabilities.md, audiences.md, experiences.md, variants.md,
manifest.md, events.md, entitlements.md

Add PHPUnit tests covering:
- serialisation
- invalid input rejection
- backwards-safe defaults
- unknown capability handling
- unknown future manifest properties

The contracts must support forward compatibility:
unknown non-critical fields should not make an otherwise compatible manifest unusable.

No UI implementation in this task.
```

---

## WORK PACKAGE 2 — Capability Registry

```text
Implement the ReactWoo Capability Registry in ReactWoo Core.

Create APIs conceptually equivalent to:
reactwoo_register_condition()
reactwoo_register_action()
reactwoo_register_context_provider()
reactwoo_register_goal()

Do not rely on WordPress 6.9 Abilities API internally.

Each capability requires:
unique ID, type, label, description, input schema, output schema where applicable,
provider/plugin, version, entitlement requirement, availability callback

Example IDs: geo.country, geo.region, visitor.device, traffic.utm, weather.temperature,
commerce.product.hide, commerce.product.promote, goal.purchase, goal.click

Expose hooks so external ReactWoo extensions can register capabilities.
Add collision detection. Never allow one plugin silently to replace a registered capability.

Create a capability inspection screen under ReactWoo > System > Capabilities.
Also create an OPTIONAL WordPress Abilities API adapter that activates only where WP supports that API.
The internal ReactWoo registry remains authoritative.

Add automated tests.
```

---

## WORK PACKAGE 3 — Decision Runtime

```text
Implement ReactWoo Decision Runtime v1 inside ReactWoo Core.

The runtime evaluates deterministic rules locally.

Inputs: Context, Audience conditions, Experience, schedule, priority, experiment assignment
Outputs: DecisionResult — matched audiences, selected experiences, selected variants, actions, reason/debug

Requirements:
1. No remote/cloud call during decision evaluation.
2. Deterministic output for identical context.
3. Explicit AND / OR condition groups.
4. Support nested condition groups.
5. Unknown capabilities must fail safely.
6. Invalid conditions must not break page rendering.
7. Experience priority must be deterministic.
8. Provide filter/action hooks around evaluation.
9. Add a debug mode available only to authorised admins.
10. Keep evaluation performance measurable.

Add unit tests for: one condition, AND, OR, nested groups, no match,
conflicting Experiences, priority, invalid capability, missing provider,
scheduled Experience, experiment assignment.

Do not implement Cloud yet.
```

---

## WORK PACKAGE 4 — Adapt current plugins to the engine

Do incrementally. Compatibility adapters first; do not delete old engines yet.

### Geo Core Pro

```text
Adapt Geo Core Pro to register its existing targeting functionality through the ReactWoo Core Capability Registry.

DO NOT remove or rewrite the existing feature implementation unnecessarily.
Create adapter classes between existing Geo Core Pro services and ReactWoo capabilities.
Register conditions such as: geo.country, geo.region, geo.city, visitor.device,
traffic.referrer, traffic.utm, weather.condition, weather.temperature, time.local
Reuse existing production-tested logic wherever possible.
Existing standalone rules must continue to work.
Where the existing rule engine duplicates new ReactWoo Core evaluation, create compatibility adapters.
Add tests showing the same existing rule produces equivalent output through the new capability system.
```

### Geo Commerce

```text
Register existing Geo Commerce functionality as ReactWoo conditions and actions.
Keep current standalone behaviour. Focus on capability adapters, not feature rewrites.

CONDITIONS: cart, customer, product, category, geographical product availability, schedule/environment
ACTIONS: show/hide/promote product, select product set, show/change offer, commerce messaging

Document every capability and its entitlement. Add regression tests.
```

### Geo Optimise

```text
Adapt Geo Optimise to the ReactWoo Core decision model.
Register: experiment assignment, conversion goals, event capture, variant attribution.
Do not yet build Cloud analytics.
Retain existing standalone testing functionality.
Ensure experiment assignment is stable for a visitor and does not change on every page request.
Add regression tests comparing existing Optimise behaviour with the shared runtime.
```

### Gate A

Same Geo rule → same result via shared runtime.

---

## WORK PACKAGE 5 — Experience Slot API

```text
Implement the ReactWoo Experience Slot API in Core.

Required properties: immutable slot ID, human-readable name, page/content reference,
adapter, available variant types, status, metadata

Create: ExperienceSlotRegistry, ExperienceSlotResolver, ExperienceSlotRenderer

Requirements:
- slot IDs stable across normal page edits
- duplicate IDs detected and regenerated safely
- cloning Elementor element must not share live slot identity
- deleted slots unavailable rather than errors
- missing slots reported diagnostically
- default website content always available as fallback

Add admin diagnostics. No Cloud yet.
```

### Gate B

Default selection leaves native design unchanged.

---

## WORK PACKAGE 6 — Elementor Adapter

```text
Create the ReactWoo Elementor Experience Adapter.
Use supported Elementor extension APIs only. Do not modify Elementor files.

Inject ReactWoo controls section into supported Elementor elements:
Use as Experience Slot [toggle], Slot name, Slot ID, Cloud status [Local / Managed]

Requirements:
1. Persist slot metadata in Elementor element settings.
2. Stable frontend ReactWoo slot marker.
3. Preserve Elementor original rendering when no variant active.
4. Allow runtime to substitute an approved Variant.
5. Editor preview remains functional.
6–7. Correctly handle duplicated and copied/pasted elements.
8. Support containers first; expand to widgets after containers stable.
9. Do not depend on DOM scraping where Elementor APIs expose required information.

Automated tests where practical; document manual Elementor regression tests.
Do not build Cloud UI in this task.
```

---

## WORK PACKAGE 7 — Gutenberg Adapter

```text
Create the ReactWoo Gutenberg Experience Slot block: reactwoo/experience-slot
Use block.json and InnerBlocks.

Attributes: slotId, slotName, managementMode

Requirements:
1. Persistent slot IDs.
2. Render default InnerBlocks normally.
3. Decision Runtime may replace slot output when Variant selected.
4. Preserve valid WordPress block markup.
5. Avoid modifying arbitrary Core block serialization.
6. Support theme.json styling of native content.
7. InspectorControls for ReactWoo settings.
8. Clearly identify Cloud-managed slots.
9. Handle deleted/missing slots gracefully.
10. SSR where required for variant selection.

Maintain compatibility with agreed minimum WordPress version.
Add editor and PHP tests.
```

---

## WORK PACKAGE 8 — ReactWoo Components / Atomic v1

```text
Refactor the architectural concept of Atomic into the ReactWoo Component System.
Do NOT simply create additional Elementor widgets.

Create platform-neutral ComponentDefinition interface.
Every component: type, schema version, props schema, design token contract,
responsive behaviour contract, accessibility contract, renderer interface.

Initial components: Hero, CTA, Promotion Banner, Notice, Product Rail, Popup/Slide-in.

Separate DATA from renderer (HeroDefinition + HeroRendererInterface →
WordPress / Elementor / Gutenberg adapters).

CSS: rw- namespace, data-rw-component, CSS custom properties. No Shadow DOM for v1.
No generic selectors. Visual states desktop/tablet/mobile. A11y tests where practical.
Every component must work without JS unless behaviour intrinsically requires JS.
```

---

## WORK PACKAGE 9 — Variant System

```text
Implement ReactWoo Variant Engine v1.

Types: DEFAULT, CONTENT, REACTWOO_COMPONENT, NATIVE_REFERENCE

Create: VariantInterface, ContentVariant, ComponentVariant, NativeReferenceVariant,
VariantResolver, VariantRenderer

Rules: invalid/missing/incompatible/error → default content.
No visitor-facing PHP fatal from Variant failure.
Structured diagnostics. Tests for every fallback scenario.
```

### Gate C

Homepage Hero: Default → Content → Component → Native without manual page edits between requests.

---

## WORK PACKAGE 10 — Cloud Connector (WordPress)

```text
Implement ReactWoo Cloud Connector in Core.
Do not implement visitor-time Cloud calls.

Implement: site registration, secure pairing, credential storage, connection state,
manifest sync, revision checking, heartbeat, capability reporting, plugin/version reporting,
disconnection, reconnect.

Security: no WP user passwords; short-lived pairing tokens; revocable site credentials;
strongest practical WP secret storage; TLS; validate payloads; reject wrong-site manifests;
replay protection where applicable.

Manifest updates atomic; current + previous known-good; validation failure retains previous.
Cloud outage must not affect visitor rendering.
Document API contracts.
```

---

## WORK PACKAGE 11 — Cloud backend

```text
Build ReactWoo Cloud v1 backend.
Low-operations SaaS control plane: stateless API, PostgreSQL, queue abstraction,
object storage only where needed, Stripe + Paystack billing adapters.
No Redis unless measured need. No microservices, K8s, real-time streaming, or per-page-view decision API.

Domain: Organisation, User, OrganisationMembership, Site, SiteConnection, Capability,
Audience, Experience, ExperienceSlot, Variant, Experiment, Goal, Manifest, ManifestRevision,
Event, Subscription, Entitlement, BrandProfile

Versioned APIs under /api/v1/
Critical: POST /sites/pair, POST /sites/confirm, GET /sites/{site}/manifest,
POST heartbeat, POST capabilities, POST /events/batch, CRUD audiences/experiences/variants/goals/experiments

Strict org/site authorisation. Migrations. Request validation. Audit log for config changes.
Automated API tests.
```

---

## WORK PACKAGE 12 — Cloud portal

```text
Build ReactWoo Cloud customer portal v1.

Nav: Overview, Audiences, Experiences, Optimise, Insights, Sites
Secondary: Brand, Team, Billing, Settings
Do not expose plugin architecture as primary navigation.

Experience wizard: Audience → Slot → Variant → Schedule → Goal → Review → Publish
Variant options: Quick Edit | Build with ReactWoo | Use Website Design

UX: one primary action per screen; surface missing capabilities and conflicts before Publish;
never publish configs the site cannot execute; Draft/Scheduled/Active/Paused; preview;
persistent site context; mobile responsive (authoring may optimise for desktop).
Visual condition builder from capability schemas — no raw condition JSON.
WCAG 2.2 AA. Loading/empty/error/disconnected states deliberate.
```

---

## WORK PACKAGE 13 — Brand profile + Component Editor

```text
Implement Brand Profile and Component Editor.
Tokens: colours, fonts, radius, spacing, content width, button appearance.
Site detection = suggestions only; customer confirms.
Constrained editor — not a page builder.
Allowed: Content, Media, Layout, Alignment, Spacing preset, Brand colours,
Typography token, Shape, Responsive arrangement.
Avoid: arbitrary positioning, z-index editor, pixel layout, unrestricted CSS, freeform DOM.
Preview must use the same component schema as production.
```

### Gate D

Cloud Audience + Experience + Variant → Publish → WP sync → qualify → variant shows; Cloud off → still works.

---

## WORK PACKAGE 14 — Events, goals, analytics

```text
WordPress: async queue, batch upload, exponential backoff, max queue size,
graceful drop/aggregate policy, no frontend latency impact.

Cloud: POST /events/batch — validate org/site/schema/type.
Initial types: experience.impression, variant.impression, goal.click, goal.lead,
commerce.add_to_cart, commerce.purchase

Daily aggregates by site/audience/experience/variant/goal.
Metrics: visitors, impressions, conversions, conversion rate, revenue where available.
Do not build session replay, heatmaps, individual profiles, real-time streaming.
Never fabricate uplift when sample size inadequate.
```

### Gate E

Control vs variant metrics with correct attribution.

---

## WORK PACKAGE 15 — Stripe + Cloud entitlements

```text
Payment processors own customer, payment method, subscription, invoice, billing lifecycle.
ReactWoo owns organisation, plan interpretation, feature entitlement, site limits.

Stripe is the first billing adapter (most markets). Paystack is the Africa adapter (WP15b).
EntitlementService — no feature code inspects Stripe or Paystack product/price/plan IDs.
Map to internal entitlements: cloud.personalisation, cloud.commerce, cloud.optimise,
cloud.components, cloud.insights, sites.max, team_members.max, history.days

Verified webhooks: authenticated, idempotent, replay-safe.
Grace/fallback on payment lapse — do not immediately delete configuration.
Prefer hosted checkout / billing portal over custom card UI.
See docs/architecture/billing-providers.md.
```

---

## WORK PACKAGE 15b — Paystack (Africa subscriptions)

```text
Add Paystack as a billing adapter beside Stripe. Do not fork EntitlementService.

Paystack owns NGN/GHS/ZAR/KES (and other Paystack-supported) collections,
cards, bank, USSD, and mobile money where Paystack offers them.
ReactWoo still maps starter|growth|scale → the same cloud.* entitlements.

Requirements:
1. PaymentProvider interface: StripeAdapter | PaystackAdapter.
2. Env catalogue: PAYSTACK_SECRET_KEY, PAYSTACK_WEBHOOK_SECRET,
   PAYSTACK_PLAN_STARTER|GROWTH|SCALE (plan codes, not amounts in feature code).
3. POST /api/v1/billing/webhooks/paystack — x-paystack-signature HMAC-SHA512,
   idempotent, replay-safe; map charge.success / subscription.* / invoice.payment_failed
   onto applyPlan, markPastDue, markCanceled.
4. Checkout: Paystack hosted page or Popup. No custom card UI.
5. Org record stores provider=paystack. v1: one processor per organisation.
6. Portal Billing: offer Paystack for Africa-based customers (billing country in
   Paystack-supported markets). Suggest Paystack there; Stripe remains available.
7. Same grace rules as Stripe. Never delete Cloud or WP configuration on lapse.
8. Heartbeat entitlement snapshot unchanged (no processor IDs).
9. Tests: signature, duplicate event, grace, cancel retains audiences/sites,
   EntitlementService public snapshot has no Paystack IDs.

Do not add Flutterwave or other processors in this package.
Do not call Paystack from WordPress or from react-cloud.
```

---

## WORK PACKAGE 16 — Existing customer migration

```text
Detect existing audiences/rules/campaigns/experiments.
Import preview. Never mutate local config merely by connecting Cloud.
Explicit "Import to ReactWoo Cloud" → translate to platform contracts →
report unsupported items → review → explicit management_mode = cloud.
Local migration backup. Disconnect must not destroy WP content.
Migration tests against representative configs.
```

---

## WORK PACKAGE 17 — Failure handling + health

```text
Report: WP/PHP/Core/extension/Woo/Elementor versions, capabilities, heartbeat,
manifest sync/revision, event queue, management mode.
Portal: Healthy | Warning | Disconnected | Configuration Error
Actionable remediation messages (not opaque error codes).
Same structured health model in WP and Cloud.
```

---

## WORK PACKAGE 18 — Security pass

```text
Security review only — no features.
Review authn/z, pairing, credentials, REST, nonces, org isolation, SQLi, XSS, CSRF, SSRF,
payload validation, manifest tampering, webhooks, secret logging, PII, event poisoning,
rate limits, replay, privilege escalation, builder escaping, component rendering, redirects, URL sanitisation.

Create docs/security/threat-model.md
Rank Critical/High/Medium/Low. Fix Critical and High. Regression tests for each fix.
```

---

## WORK PACKAGE 19 — Performance pass

```text
Profile Decision Runtime: baseline, 1/10/50/100 audiences, multiple experiences/slots.
Identify DB/remote/repeated context/JSON/render costs.
Requirements: zero Cloud requests in visitor lifecycle; no unnecessary manifest reparsing;
lazy context providers; expensive providers use cached data; only evaluate capabilities
required by candidate Experiences.
Benchmarks + docs. Optimise measured bottlenecks only.
```

---

## WORK PACKAGE 20 — AI recommendations (deliberately last)

```text
Advisory only. AI must NOT directly alter a live customer site.
Output Recommendation with observation, evidence, suggested action, proposed Experience/Variant,
confidence explanation. Explicit customer approval required.
No unnecessary personal visitor data to LLM. Structured outputs. Record model/provider/time/dataset/action.
No autonomous optimisation in this phase.
```

---

## Sequence checklist

- [x] WP0 audit (2026-08-11)  
- [x] WP1 contracts (2026-08-11) — `includes/contracts/`, `docs/contracts/`, `composer test:contracts`  
- [x] WP2 capability registry (2026-08-11) — `includes/platform/`, admin Capabilities screen, `composer test:platform-capabilities`  
- [x] WP3 decision runtime (2026-08-11) — `includes/decision/`, `composer test:decision-runtime` (not on visitor render path; Gate A via adapters is WP4)  
- [x] WP4 satellite adapters (2026-08-11) — Pro/Commerce/Optimise capability registration; Gate A `composer test:decision-parity`  
- [x] WP5 Experience Slot API (2026-08-11) — `includes/slots/`, admin Experience Slots, `composer test:experience-slots` (Gate B foundation; Elementor adapter is WP6)  
- [x] WP6 Elementor Experience Slot adapter (2026-08-11) — containers/sections, clone-safe binding, frontend markers + Gate B buffer; `composer test:elementor-experience-slots`  
- [x] WP7 Gutenberg Experience Slot block (2026-08-11) — `reactwoo/experience-slot`, InnerBlocks, clone-safe instanceId; `composer test:gutenberg-experience-slot`  
- [x] WP8 ReactWoo Component System v1 (2026-08-11) — definitions + php_html renderer for six types; `composer test:components` (builder adapters via Variants WP9)  
- [x] WP9 Variant Engine (2026-08-11) — DEFAULT/CONTENT/COMPONENT/NATIVE + fallbacks; Gate C via `composer test:variants`  
- [x] WP10 Cloud Connector (2026-08-11) — pairing, encrypted credentials, atomic manifest cache, cron/admin sync only; `composer test:cloud-connector`  
- [x] WP11 Decision Cloud backend (2026-08-12) — new service `reactwoo-decision-cloud` (`/api/v1` pair/confirm/manifest/heartbeat/capabilities/events + CRUD); `npm test`  
- [x] WP12 Cloud portal (2026-08-12) — `/portal` shell, site context, experience wizard, visual conditions, publish-check; `npm test`  
- [x] WP13 brand/component editor (2026-08-13) — Brand Profile tokens + constrained Component Editor (same WP8 schema/preview); Decision Cloud `0.3.0` + Geo Core presentation attrs  
- [x] WP14 events/goals/analytics (2026-08-13) — WP async queue + Cloud `POST /events/batch` validation, daily aggregates, Insights (no fabricated uplift); Decision Cloud `0.4.0`  

- [x] WP15 Stripe + entitlements (2026-08-14) — Decision Cloud `0.5.0` billing webhooks + `EntitlementService`; Geo Core `RWGC_Entitlements` facade (`composer test:entitlements`)  
- [x] WP15b Paystack Africa billing (2026-08-14) — Decision Cloud `0.6.0` Paystack adapter + webhooks; same entitlements (`docs/architecture/billing-providers.md`)  
- [x] WP16 Existing customer migration (2026-08-14) — Geo Core `1.8.152` detect/preview/import/switch; Decision Cloud `0.7.0` `POST /migration/import` + `POST /management-mode`; pairing never flips mode (`composer test:cloud-migration`)  
- [x] WP17 Failure handling + health (2026-08-14) — Geo Core `1.8.153` `RWGC_Cloud_Health`; Decision Cloud `0.8.0` `GET /sites/:id/health`; statuses Healthy / Warning / Disconnected / Configuration Error (`composer test:cloud-health`)  
- [x] WP18 Security pass (2026-08-14) — Geo Core `1.8.154` HMAC credentials + SSRF host block; Decision Cloud `0.9.0` portal token, pairing rate limit, checkout URL allowlist (`docs/security/threat-model.md`, `composer test:cloud-security`)
- [x] WP19 Performance pass (2026-08-14) — Geo Core `1.8.155` candidate-only audiences, lazy context, manifest memo, Cloud HTTP blocked after `template_redirect` (`docs/performance/decision-runtime.md`, `composer test:decision-perf`)
- [x] WP20 AI recommendations (2026-08-14) — Decision Cloud `0.10.0` advisory generate/approve/dismiss; Geo Core `1.8.156` recommendation contract + admin cache (`docs/contracts/recommendations.md`, `composer test:recommendations`). Approve saves drafts only — never compiles a live manifest.
- [x] Sprint 1 WooCommerce commerce (2026-08-14) — Decision Cloud `0.11.0` removes Stripe/Paystack adapters; store handoffs + `POST /billing/webhooks/woocommerce` (`docs/architecture/commerce-and-onboarding.md`)
- [x] Sprint 2 Store companion bridge (ReactWoo.com) — checkout meta, activation claims, subscription webhooks
- [x] Sprint 3 Cloud activation and workspace provisioning (`0.12.0`)
- [x] Sprint 5 Standalone upgrade (`0.13.0`)
- [x] Sprint 4 Guided site connection (`0.14.0`)
- [x] Sprint 6 Billing resilience (`0.15.0`)
- [ ] WP10–13 Cloud → **Gate D** (end-to-end site still needed)  
- [ ] WP14–16 analytics/billing/migration → **Gate E** (metrics pipeline in place; live attribution still needed)  
