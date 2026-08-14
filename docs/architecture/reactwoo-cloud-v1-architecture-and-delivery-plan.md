# ReactWoo Cloud — Official Product & Technical Plan (v1)

**Status:** Canonical reference for Cursor and product engineering.  
**Last formalised:** 2026-08-11  
**Work packages:** [work-packages.md](./work-packages.md)

---

## 1. Product vision

### Standalone ReactWoo

Individually sellable, run locally in WordPress, annual licence model:

| Product | Role |
|---------|------|
| ReactWoo Core (Geo Core) | Free foundation |
| Geo Core Pro | Advanced targeting / personalisation |
| Geo Commerce | Commerce targeting and merchandising |
| Geo Optimise | Experimentation and conversion optimisation |
| Atomic / ReactWoo Components | Component capability |

### ReactWoo Cloud

One SaaS subscription provides Pro capabilities plus:

- central management
- shared audiences, experiences, goals
- experimentation and reporting
- component variants
- cross-plugin decisions
- multi-site
- AI recommendations later
- future integrations per plan

Cloud is **not** merely bundling plugins. It introduces the loop standalone plugins cannot provide alone:

> **Audience → Context → Decision → Experience → Goal → Insight**

### Critical architectural decision

> **There is one ReactWoo decision model, but two parts to its execution. Cloud authors and compiles decisions; ReactWoo Core executes them locally.**

That preserves the “one decision engine” proposition without a cloud request on every visitor page load.

---

## 2. Architecture

```text
                     REACTWOO CLOUD
┌─────────────────────────────────────────────────────────┐
│  Organisations      Sites          Billing              │
│  Audiences          Experiences    Goals                │
│  Variants           Experiments    Insights             │
│             REACTWOO DECISION SERVICE                   │
│               Validates + Compiles                      │
│                        ↓                                │
│              Versioned Manifest                         │
└────────────────────────┬────────────────────────────────┘
                         │ secure sync
                         ▼
┌─────────────────────────────────────────────────────────┐
│                    REACTWOO CORE                        │
│  Cloud Connector · Entitlements · Capability Registry   │
│  Decision Runtime · Manifest Cache · Event Queue        │
│  Component Runtime                                      │
└───────┬──────────────────┬─────────────────┬────────────┘
        ▼                  ▼                 ▼
 Geo Core Pro        Geo Commerce      Geo Optimise
 conditions          actions           experiments
 targeting           products          goals
        │
        ├──────── Elementor Adapter
        ├──────── Gutenberg Adapter
        └──────── ReactWoo Components
```

---

## 3. One engine, not multiple plugin engines

ReactWoo Core owns **Context**, **Conditions**, **Actions**, and **Outcomes**.  
Each plugin **registers capabilities** only.

Examples:

**Geo Core Pro — conditions:** `geo.country`, `geo.region`, `geo.city`, `visitor.device`, `visitor.returning`, `traffic.source`, `traffic.referrer`, `traffic.utm`, `weather.condition`, `weather.temperature`

**Geo Commerce — conditions:** `commerce.cart_value`, `commerce.cart_contains`, `commerce.product`, `commerce.category`, `commerce.customer_value`  
**Actions:** `commerce.product.show|hide|promote`, `commerce.offer.show`, `commerce.message.replace`

**Optimise:** `experiment.assign`, `goal.purchase`, `goal.add_to_cart`, `goal.click`, `goal.lead`, `goal.custom`

Cloud never asks “is Geo Commerce installed?” — it asks “does this site provide `commerce.product.promote`?”

---

## 4. Core as mandatory infrastructure

All commercial ReactWoo plugins progressively depend on Core (still free). Core owns:

Capability Registry · Decision Runtime · Context Registry · Action Registry · Event Bus · Cloud Connection · Cloud Sync · Manifest Storage · Entitlement Provider · Experience Slots · Component Runtime · Health Reporting

Do not rebuild these inside satellites.

---

## 5. Do not make WP Abilities the internal architecture

ReactWoo owns an internal capability API. Optionally bridge to WordPress Abilities (WP 6.9+) for AI/MCP later. Internal registry remains authoritative; do not raise minimum WP to 6.9 for this.

```text
ReactWoo Capability → WordPress Ability → MCP / AI Agent
```

---

## 6. Central data model

Agree before Cloud development. Core entities:

| Entity | Role |
|--------|------|
| Organisation | Customer/company + plan |
| Site | Connected installation + `manifestRevision` |
| Audience | Who (condition tree by capability IDs) |
| Experience | What should happen (audience, priority, schedule, status) |
| Experience Slot | Where (page + adapter) |
| Variant | Content / `reactwoo_component` / `native_reference` |
| Goal | Success definition |
| Experiment | Allocation control |
| Event | Measurement payload |

See work package WP1 for contract JSON shapes and schema version `reactwoo_schema_version = 1`.

---

## 7. Manifest

Cloud does **not** send raw DB structures to WordPress. It compiles a versioned manifest:

```json
{
  "schema": "1.0",
  "revision": 142,
  "site": "site_123",
  "audiences": [],
  "experiences": [],
  "variants": [],
  "experiments": [],
  "goals": []
}
```

Core downloads, validates, stores `manifest_current` / `manifest_previous`, swaps atomically. Invalid revision → keep previous. Never destroy live experience for bad Cloud config.

---

## 8. Cloud not required during page rendering (non-negotiable)

```text
visitor → WordPress → ReactWoo Core → local context → cached manifest → decision → render
```

**Not:** visitor → WordPress → ReactWoo API → wait → render.

Benefits: low SaaS cost, low latency, Cloud outage does not take down sites, normal WP hosting compatibility.

---

## 9. Variant architecture

| Type | Behaviour |
|------|-----------|
| **Content** | Change safe content props; native builder keeps layout/typography/CSS |
| **ReactWoo Component** | Platform-neutral component schema + props (Atomic evolves here) |
| **Native Reference** | Render default OR builder template/post ID (Elementor/Gutenberg) |

---

## 10. Experience Slots

Stable named locations for alternative content.

- **Elementor:** inject “Use as Experience Slot” control on supported elements; persist metadata; stable ID (e.g. `rw_homepage_hero_8ds91`).
- **Gutenberg:** `reactwoo/experience-slot` wrapper block with InnerBlocks — do not attach to every core block initially.

---

## 11. ReactWoo Components / Atomic

Atomic’s architectural meaning shifts from “Elementor widgets” to **ReactWoo Component System**:

```text
Component Schema → Elementor / Gutenberg / WP / Cloud Preview / Headless (later)
```

**Initial library (six only):** Hero, CTA, Promotion Banner, Notice, Product Rail, Popup / Slide-in.

---

## 12–14. Design tokens, CSS isolation, accessibility

- Brand tokens: `--rw-color-*`, `--rw-font-*`, `--rw-radius-*`, `--rw-space-*`, `--rw-content-width`.
- Cloud may detect candidate values; customer confirms Brand Profile.
- No Shadow DOM in v1; `rw-` naming, `data-rw-component`, CSS variables, cascade layers; no generic `.button`/`.hero` selectors.
- Components target **WCAG 2.2 AA** (semantics, keyboard, focus, reduced motion, contrast, touch targets).

---

## 15. Cloud portal IA

Primary: Overview · Audiences · Experiences · Optimise · Insights · Sites  
Secondary: Brand · Team · Billing · Settings  

**Do not** expose Geo Core / Commerce / Optimise / Atomic as primary nav.

---

## 16. Experience wizard

1. Who? (Audience)  
2. Where? (Slot)  
3. What? (Quick Edit / ReactWoo component / Website design)  
4. When? (Schedule)  
5. Measure (Goal)  
6. Publish → validate → compile manifest → site sync  

---

## 17. Decision conflict handling

Deterministic precedence: Eligibility → Schedule → Audience match → Priority (0–100) → Specificity → Stable tie-breaker (id/creation). Never random. Cloud flags conflicts before publish.

---

## 18. Experiment assignment

Stable: `hash(experimentId + anonymousVisitorId)` → bucket. Do not re-randomise per page load. Minimal anonymous ID; no email required.

---

## 19. Privacy

Minimise by default. No names/email/full IP retention/customer profiles for basic personalisation. Geo: resolve → discard/anonymise. Analytics around anonymous visitor + audience/experience/variant/event/value/timestamp. Consent-aware telemetry in runtime.

---

## 20–22. Connection, sync, events

- WP initiates pairing with one-time token; Cloud returns revocable site credentials.
- `GET /sites/{id}/manifest` with conditional revision (304 when unchanged).
- Local event queue → `POST /events/batch` with retry/backoff; Cloud down → queue within limits; never block render.

---

## 23–25. Billing, entitlements, managed mode

- Payment processors own subscription billing. **Stripe** (WP15) is the default rail; **Paystack** (WP15b) is the Africa rail (NG, GH, ZA, KE, and other Paystack-supported markets). ReactWoo owns product entitlements (`cloud.personalisation`, `cloud.commerce`, …, `sites.max`).
- One organisation, one processor at a time in v1. Internal plans stay `starter` | `growth` | `scale` on both rails.
- Core: `EntitlementProviderInterface` → StandaloneLicenseProvider | CloudEntitlementProvider. Feature code asks `$entitlements->allows('…')` only — never Stripe or Paystack IDs.
- Cloud-managed sites: WP shows “Managed by ReactWoo Cloud”; primary editing in Cloud to avoid dual source of truth.
- Detail: [billing-providers.md](./billing-providers.md).

---

## 26. Migration

On connect: detect local audiences/rules/experiments → import preview → explicit import → review → explicit switch to `management_mode = cloud`. Never silent source-of-truth change. Preserve local backup; disconnect must not destroy WP content.

---

## Build order

| Phase | Packages |
|-------|----------|
| Foundation | 0 → 1 → 2 → 3 |
| Convert suite | 4 |
| Rendering (local product without Cloud) | 5 → 6 → 7 → 8 → 9 |
| Cloud | 10 → 11 → 12 → 13 |
| SaaS value | 14 → 15 → 15b → 16 |
| Hardening | 17 → 18 → 19 |
| Intelligence | 20 |

Do **not** give Cursor the whole platform at once. One work package at a time.

---

## Milestone gates

| Gate | Must demonstrate |
|------|------------------|
| **A — Shared engine** | Same geo rule → same result via shared runtime; no regression |
| **B — Slot** | Default selection leaves Elementor/Gutenberg design unchanged |
| **C — Variant** | Default → Content → Component → Native on one slot without manual page edits |
| **D — Cloud** | Author in Cloud → Publish → sync → qualify → variant shows; Cloud off → still works |
| **E — Measurement** | Control vs variant metrics with correct attribution |

---

## MVP boundary

**V1 must:** connect sites, detect capabilities, audiences, slots, experiences, content/component/native variants, Elementor, Gutenberg, scheduling, goals, experiments, basic analytics, Cloud subscription (Stripe and/or Paystack), brand profile.

**Not V1:** headless hosting, AI websites, autonomous optimisation, heatmaps, session replay, CRM/CDP, email automation, edge execution, mobile SDK, Shopify, full-page builder, ReactWoo hosting, real-time dashboards.

---

## Immediate suite rule (before Cloud)

Stop adding major features into plugin-specific rule architectures. Ask: Context, Condition, Action, or Goal? If yes → register with Core once (`visitor.language`, etc.).

---

## References

- Work packages: [work-packages.md](./work-packages.md)
- Billing processors: [billing-providers.md](./billing-providers.md)
- Platform Cursor rule: `.cursor/rules/reactwoo-platform.mdc` (all product repos)
- Legacy Geo Core phases: [`../geo-core-cursor-master-plan.md`](../geo-core-cursor-master-plan.md)
- WordPress Abilities API, Elementor widgets/controls, block registration, Interactivity API, Stripe / Paystack billing — external platform docs used for adapter design only
