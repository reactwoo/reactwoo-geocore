# ReactWoo suite — current-state audit (WP0)

**Status:** Complete (read-only audit; no code changes)  
**Date:** 2026-08-11  
**Canonical plan:** [reactwoo-cloud-v1-architecture-and-delivery-plan.md](./reactwoo-cloud-v1-architecture-and-delivery-plan.md)

> Terminology note: today’s free foundation product is shipped as **ReactWoo Geo Core** (`reactwoo-geocore`). The Cloud v1 plan refers to it as **ReactWoo Core**. Same plugin; naming will converge as platform APIs land.

---

## 1. Suite inventory (workspace)

| Product | Path | Bootstrap | Version (audit) | Role today |
|---------|------|-----------|-----------------|------------|
| Geo Core | `reactwoo-geocore` | `reactwoo-geocore.php` | 1.8.127 | Free geo engine, portable targeting, suite shell, REST discovery |
| GeoCore Pro | `reactwoo-geocore-pro` | `reactwoo-geocore-pro.php` | 0.1.51 | License, attribution, profiles, weather, Pro portable conditions |
| Geo Commerce | `reactwoo-geo-commerce` | `reactwoo-geo-commerce.php` | 0.3.25 | WooCommerce pricing/overlays/fees/merchandising |
| Geo Optimise | `reactwoo-geo-optimise` | `reactwoo-geo-optimise.php` | 0.4.92 | Experiments, goals, events; embeds former Geo AI (`merged-geo-ai/`) |
| Atomic Free | `reactwoo-atomic` (app under `apps/reactwoo-atomic/`) | `reactwoo-atomic.php` | 0.7.0 | Elementor Atomic widgets (Free) |
| Atomic Pro | `reactwoo-atomic-pro` / monorepo Pro app | `reactwoo-atomic-pro.php` | (edition) | Pro Atomic widgets; standalone, contains Free |
| React Cloud | `react-cloud` (wooalisync) | `server.js` | 1.1.11 | Node Express: GooRev OAuth, Geo Google Ads/GA/GTM helpers |
| React License | `react-license` | Express app | — | License JWT / WHMCS entitlements |
| ReactWoo API | `reactwoo-api` | TypeScript API | — | Updates publish, Geo AI workflows, license-gated routes |
| Reviews | `reactwoo-reviews` | plugin | — | GBP reviews (adjacent; not Cloud decision path) |
| Flow | `reactwoo-flow` | plugin | — | Internal ops orchestration |
| API Manager | `reactwoo-api-manager` | plugin | — | Storefront/license UI (production SSH origin) |

**Shared minimums (Geo family):** WordPress 6.2+, PHP 7.4+. Satellites declare `Requires Plugins: reactwoo-geocore` (Commerce also WooCommerce). Boot order: Core `plugins_loaded` priority **5**; Pro/satellites **20**.

**Namespaces:** Mostly global `RWGC_*` / `RWGCP_*` / `RWGCM_*` / `RWGO_*` / `RWGA_*` classes (not PSR namespaces), except Atomic (`ReactWoo\Atomic\…`).

---

## 2. ReactWoo Geo Core

### Bootstrap & layout

- Main: `reactwoo-geocore.php` → `RWGC_Plugin::boot()`
- Folders: `includes/` (`engine/`, `rules/`, `targeting/`, `context/`, `events/`, `compat/`, `ai/`, …), `admin/`, `blocks/`, `assets/`, `tests/`

### Storage

- Options via `RWGC_Settings` (geo cache, MaxMind, suite flags, REST toggle)
- CPT: `rwgc_visibility_rule` (portable visibility rules)
- Legacy CPT compat: `includes/compat/class-rwgc-legacy-geo-rule-cpt.php`
- Post meta for page versions / routing (`RWGC_Page_Version*`)
- Elementor document/element settings for geo visibility (`egp_*` / `rwgc_*` keys; Atomic geo via `RWGC_Elementor_Atomic_Geo`)
- Gutenberg block `geo-content` with `portableTargeting` attribute

### Rule evaluation flow (today’s “decision engine”)

1. Build **`RWGC_Context_Snapshot`** (visitor geo, device, UTM, etc.)
2. Sanitize portable JSON via **`RWGC_Targeting_Rule_Set_Schema`**
3. Evaluate via **`RWGC_Rule_Evaluator::matches()`** (also `RWGC_Targeting_Rule_Set_Evaluator`, surface evaluators)
4. Built-in condition types include: `country`, `country_group`, `language`, `locale`, `device`/`device_type`, time/day, `logged_in`, page/URI, UTM fields, nested `condition_group`
5. Extension hooks: `rwgc_rule_condition_resolvers`, `rwgc_targeting_evaluate_condition` (Pro)
6. Page routing: `RWGC_Routing` / engine `RWGC_Page_Route_*` + `RWGC_Variant` / fallback resolver

**Important:** This is already a **shared portable evaluator**, but IDs are short slugs (`country`, `device`) — not yet Cloud capability IDs (`geo.country`, `visitor.device`). There is no Capability Registry, Manifest, Experience, or Slot abstraction.

### REST (`reactwoo-geocore/v1`)

Public/discovery (when REST enabled): `/location`, `/capabilities`  
Admin/assistant: `/ai/variant-draft`, `/targeting/interpret`, `/targets/search|create`, rule-tester/preview routes  

`/capabilities` already reports satellites (`geo_ai`, `geo_optimise`, `geo_commerce`), hooks, event types — a natural precursor to Cloud capability reporting, but **not** the WP0+ capability contract.

### Builder integrations

- **Elementor:** document/widget geo visibility + portable rules; Atomic V4 geo controls
- **Gutenberg:** geo-content block + portable targeting attribute
- Shortcodes / suite admin shell for satellites

### Licensing

- Core is **free** (no product license for geo). Updates via free slug on `api.reactwoo.com`.
- Optional AI credentials for API add-ons only.

### Frontend rendering

Visitor → MaxMind/context → rule/route decision → show/hide content or swap page variant. **No cloud call required** for core geo (aligned with Cloud v1 non-negotiable).

---

## 3. GeoCore Pro

### Bootstrap

`RWGCP_Bootstrap::init` @ priority 20. Classes: license, cloud client, profile sync/matcher, provider registry (Google Ads/GA, Meta, LinkedIn, generic CPC), weather stack, portable targeting bridge, admin.

### Dependencies

Requires Geo Core. Talks to **license.reactwoo.com** + **react-cloud** (Google OAuth tokens, GA audiences, Ads campaigns, GTM helpers).

### Targeting contribution

`RWGCP_Portable_Targeting` registers Pro condition types via `rwgc_targeting_evaluate_condition` (`campaign`, `audience`, `reactwoo:*`, weather facets, etc.). Does **not** fork a second evaluator.

### Licensing

`RWGCP_License` — cached token validity gates cloud features. Admin “License & cloud” is **license + Google/cloud OAuth**, not the future Cloud Decision Service / manifest sync.

### Risk for Cloud

Name collision: “ReactWoo Cloud” already appears in Pro UX for Google/license auth. Future Decision Cloud must use distinct product language and endpoints.

---

## 4. Geo Commerce

### Bootstrap

`RWGCM_Plugin` @ 20. Custom tables via `RWGCM_DB` (rules + overlays). Activation installs schema.

### Decision / outcomes

- Eligibility: **`RWGCM_Targeting_Adapter`** → maps legacy condition rows → Core portable schema → **`RWGC_Rule_Evaluator`**
- Own action stack: pricing, overlays, fees, coupons, shipping, product show/hide/promote, weather merchandising (`RWGCM_Action_*`, catalog price classes, weather*)
- Still has **`RWGCM_Rule_Evaluator`** / condition library for commerce-specific paths — partial duplication of “engine” concepts (outcomes), even when eligibility is Core

### Storage

- Custom tables: rules, overlays
- Product/order meta for geo/weather
- License settings UI (per-product key; import patterns shared with Optimise)

### Builder

Gutenberg weather/commerce display helpers; Elementor primarily via Core targeting + Woo templates.

---

## 5. Geo Optimise (+ merged Geo AI)

### Bootstrap

`RWGO_Plugin` @ 20. Activation: goal events table, redirects/promos schema, AI module tables.

### Experiments & goals

- CPT: `rwgo_experiment` (`RWGO_Experiment_CPT`)
- Sticky assignment: **`RWGO_Assignment`** (cookie `rwgo_ab`, first-assignment pick + persist — already stable across page loads)
- Goals: registries/services, Elementor/Gutenberg goal wiring, GTM handoff
- Events: `RWGO_Event_Store`, core event bridge, exposure/measurement stampers
- Tables: goal events, redirects, promos; plus many **merged-geo-ai** tables (analysis, recommendations, drafts, site/page context, UX insights, AI runs, …)

### Licensing / cloud

Per-plugin license UI; `RWGO_Cloud_Client`; AI uses `RWGA_License` / platform client → `api.reactwoo.com` + `license.reactwoo.com`. Site intelligence sync is **outbound analytics/AI**, not inbound decision manifests.

### Duplication risk

Optimise owns experiment assignment + goals + local analytics. Cloud v1 will need adapters so Cloud Experiments/Goals map onto these without a second assignment algorithm.

---

## 6. Atomic / components

### Today

- Elementor **4.1.5+ Atomic** widgets only (`ReactWoo\Atomic`)
- Free + Pro monorepo; Pro standalone and wins when both active
- Large Free catalogue (Carousel, Off-Canvas, Modal, Smart Menu, …) and Pro dynamic/commerce widgets (Product Grid, Live Search, Cart Drawer, …)
- Design authority: Figma ReactWoo file; CSS/class contracts for Atomic V4
- **Not** a platform-neutral ComponentDefinition with multi-renderer adapters
- **No** Experience Slot concept; widgets are authoring primitives, not Cloud variants

### Cloud plan implication

WP8 reframes Atomic as **ReactWoo Component System** with six starter components for Cloud variants — orthogonal to shipping every existing Elementor widget into Cloud. Existing Atomic work remains valuable as Elementor renderer/adaptor source, not as the universal schema.

---

## 7. Licensing (cross-cutting)

| Layer | Implementation |
|-------|----------------|
| Authority | `react-license` (JWT + WHMCS entitlements) |
| Product API | `reactwoo-api` (updates, Geo AI workflows, entitlement guards) |
| Plugin clients | `RWGCP_License`, Commerce/Optimise license UIs, `RWGA_License` |
| Pattern | Per-product annual keys + JWT; scattered `is_licensed` / feature checks |
| Gap vs plan | No unified `EntitlementProviderInterface` with Standalone vs Cloud providers; no `cloud.*` entitlement map |

---

## 8. Audience / campaign / rule models (today)

| Concept | Where it lives | Shape |
|---------|----------------|-------|
| Portable visibility rules | Core CPT + Elementor/Gutenberg attrs | JSON: `schema_version`, `enabled`, `mode`, `match`, `rules[]`/`conditions[]` |
| Page versions / routes | Core routing engine | Country (baseline) → page variant |
| Pro audiences/campaigns | Pro profile sync + portable condition types | Synced entities + snapshot enrichment |
| Commerce rules/overlays | Custom tables + meta | Legacy rows bridged to portable evaluator |
| Experiments | Optimise CPT + cookies | Variant A/B (+ weights) |
| AI recommendations | Optimise `merged-geo-ai` tables | Advisory drafts (local/API) — not Cloud Experiences |

There is **no** first-class Audience / Experience / ExperienceSlot / Manifest entity matching the Cloud data model.

---

## 9. Elementor & Gutenberg (summary)

| Surface | Core | Pro | Commerce | Optimise | Atomic |
|---------|------|-----|----------|----------|--------|
| Elementor visibility/rules | Yes | Extends conditions | Uses Core eligibility | Goals / page goals | Widgets |
| Gutenberg | geo-content block | — | weather/display | goal blocks | — |
| Experience Slot | **No** | No | No | No | No |

---

## 10. Tracking / events

- Core: event type discovery via capabilities/hooks; assignment events for satellites
- Optimise: primary goal event store + frontend tracking; GTM provision/handoff via react-cloud
- Commerce: attribution / order geo meta
- **No** batched Cloud Decision event pipeline (`POST /events/batch` for experience/variant impressions)

---

## 11. Existing “cloud” services (do not confuse)

| Service | Host | Purpose today |
|---------|------|---------------|
| react-cloud | `cloud.reactwoo.com` (Express) | Reviews OAuth, Geo Google Ads/GA/GTM token vault |
| react-license | `license.reactwoo.com` | License activation / JWT entitlements |
| reactwoo-api | `api.reactwoo.com` | Updates R2 publish, managed AI, site intelligence |

**None of these** implement Organisation → Site → Manifest → Decision authoring. Cloud v1 Decision Service is a **new control plane** (WP11), possibly evolving from or sitting beside these services — must not overload visitor paths or silently replace license/Google token APIs.

---

## 12. Duplicated / shared utilities

| Area | Shared today | Still duplicated |
|------|--------------|------------------|
| Portable condition evaluation | `RWGC_Rule_Evaluator` | Commerce action/outcome engines; Optimise experiment engine |
| Context snapshot | Core | Satellites re-read context for their UIs |
| License activate UI | Similar patterns | Separate classes per product |
| Suite admin shell | Core registers routes/CSS | Each satellite own menus/views |
| Cloud HTTP clients | Similar patterns | `RWGCP_*`, `RWGO_*`, `RWGA_*`, `RWGCM_*` clients |
| Updater | Shared satellite updater pattern | Per-slug registration |
| AI | Merged into Optimise | Legacy references / snapshot providers in Core/Pro/Commerce |

---

## 13. Public hooks (representative)

Documented suite hooks: `docs/GEO_SUITE_HOOKS.md`. Critical ones for Cloud migration:

- `rwgc_rule_condition_resolvers`
- `rwgc_targeting_evaluate_condition`
- `rwgc_geo_data` / context filters
- `rwgc_page_route_bundle`, `rwgc_route_variant_decision`
- Satellite load: `rwgo_loaded`, `rwgcm_loaded`, `rwga_loaded`
- Optimise: `rwgo_variant_assigned`, goal/event actions

**Constraint:** Do not rename these without compatibility shims.

---

## 14. What already aligns with Cloud v1

1. Local evaluation only for visitor decisions (Core + satellites).
2. Portable rule schema + single Core matcher used by Commerce/Pro.
3. Stable experiment assignment (cookie-backed).
4. REST `/capabilities` satellite discovery.
5. Builder integrations that preserve default content when rules don’t match.
6. Free Core as required dependency for commercial satellites.

## 15. What is missing for Cloud v1

1. Capability Registry with dotted IDs + collision rules + entitlement metadata.
2. Shared contracts (Audience, Experience, Slot, Variant, Manifest, Event, Entitlement).
3. Decision Runtime that consumes **manifests** (not only local CPT/meta).
4. Experience Slots + Content / Component / Native variant engine.
5. Platform-neutral Component schema (Atomic reframed).
6. Cloud Connector (pairing, atomic manifest swap, event batch).
7. Unified entitlement provider (standalone vs Cloud).
8. Authoring portal + PostgreSQL Decision backend (separate from current react-cloud Google vault).

---

## Per-plugin checklist (compact)

See [cloud-migration-impact.md](./cloud-migration-impact.md) for risks and migration order. WP1 should begin only after this audit is accepted.
