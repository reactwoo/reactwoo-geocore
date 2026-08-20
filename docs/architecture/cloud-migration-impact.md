# Cloud migration impact (WP0)

**Companion:** [current-state.md](./current-state.md)  
**Plan:** [reactwoo-cloud-v1-architecture-and-delivery-plan.md](./reactwoo-cloud-v1-architecture-and-delivery-plan.md)

---

## 1. Constraints (must not break)

1. Existing standalone Geo Core / Pro / Commerce / Optimise behaviour continues without Cloud.
2. Stored portable rules, Commerce rules/overlays, Optimise experiments, and Elementor/Gutenberg settings remain loadable.
3. Public hooks/classes (`RWGC_Rule_Evaluator`, `rwgc_targeting_*`, satellite load hooks) are not renamed without shims.
4. Visitor page render never depends on a Cloud round-trip.
5. License.reactwoo.com / api.reactwoo.com / existing react-cloud Google OAuth must keep working during parallel introduction of Decision Cloud.

---

## 2. Decision logic that should move toward Core

| Today | Target (Cloud plan) | Migration style |
|-------|---------------------|-----------------|
| `RWGC_Rule_Evaluator` + resolvers | Capability Registry + Decision Runtime condition eval | **Evolve in place** — map slugs → capability IDs; keep old slugs as aliases |
| Pro `rwgc_targeting_evaluate_condition` | Pro registers capabilities | Adapter wrappers around existing resolvers |
| Commerce eligibility via `RWGCM_Targeting_Adapter` | Commerce registers conditions/actions | Keep adapter; register capabilities that call existing code |
| Commerce action appliers | Action capabilities | Register, don’t rewrite pricing math |
| Optimise `RWGO_Assignment` | Experiment assignment in Decision Runtime | Bridge: Runtime calls Optimise assignment or shared hash helper; cookie/anonymous ID policy must stay stable |
| Optimise goals/events | Goal/Event capabilities + later Cloud batch | Register types; keep local store until WP14 |
| Page routing / variants | Experience Slot + Native/Content variants (longer term) | **Do not** rip out `RWGC_Routing` in WP1–4; coexist |
| Atomic Elementor widgets | Component schema + renderers | Parallel track from WP8; don’t block Gates A–C on full Atomic catalogue |

---

## 3. Unsafe or high-friction areas

### 3.1 Dual meaning of “Cloud”

Pro admin already says “License & cloud” for Google/license auth via **react-cloud**. Decision Cloud must use distinct:

- product name in UI (“ReactWoo Cloud” control plane vs “Google connection”)
- API base URLs / paths
- credential types (site pairing secrets ≠ license JWT ≠ Google refresh tokens)

**Risk:** Operators revoke the wrong credential or agents wire manifest sync to the Google vault.

### 3.2 Multiple entitlement checkers

Scattered license classes make `if ( $cloud || $geo_pro_license )` likely unless WP1–2 introduce a single entitlement facade early (even stubbed).

### 3.3 Condition ID vocabulary mismatch

Portable rules use `country`, `device`, `campaign`. Cloud plan uses `geo.country`, `visitor.device`, …  

**Risk:** Publishing Cloud manifests that Core cannot evaluate, or breaking local rules if IDs are renamed naively.

**Mitigation:** Bidirectional alias map in contracts (WP1) + Capability Registry (WP2).

### 3.4 Optimise + AI table surface area

`merged-geo-ai` has many tables and sync paths. Cloud Insights (WP14) must not assume those tables are the source of truth; import carefully (WP16).

### 3.5 Atomic scope creep

Atomic already has dozens of Elementor widgets. Cloud MVP needs **six** components.  

**Risk:** Refactoring the whole Atomic monorepo before Slots/Variants exist.

**Mitigation:** WP8 builds ComponentDefinition + six components; Elementor widgets remain product catalogue.

### 3.6 Elementor Atomic V4 vs classic

Core already has separate classic vs Atomic geo control paths. Experience Slot adapter (WP6) must cover both or explicitly phase classic first (plan says containers first).

### 3.7 react-cloud stack vs WP11 backend

Current react-cloud is Express + JSON file token store. Plan specifies TypeScript + PostgreSQL Decision Service.  

**Options:** new service, or major evolve of react-cloud. Either way, **do not** put Decision APIs into the Google OAuth routes without isolation.

### 3.8 Source-of-truth conflicts

Sites will have local CPT/rules **and** (later) Cloud manifests. Without managed mode (WP25/WP16), dual editing will diverge.

**Mitigation:** Explicit `management_mode`; import preview; never flip on connect alone.

---

## 4. Recommended migration order (engineering)

Matches plan gates; refined by audit:

| Order | Package | Why this order given current code |
|-------|---------|-----------------------------------|
| 1 | **WP0** | Done — this audit |
| 2 | **WP1 contracts** | Alias map for existing slugs ↔ capability IDs; Manifest/Audience shapes |
| 3 | **WP2 Capability Registry** | Thin wrap over `rwgc_rule_condition_resolvers` + new register_* APIs |
| 4 | **WP3 Decision Runtime** | Evaluate Audiences/Experiences locally; can start with in-memory/fixtures before Cloud |
| 5 | **WP4 adapters** | Pro → Commerce → Optimise capability registration; Gate A vs `RWGC_Rule_Evaluator` |
| 6 | **WP5–7 Slots + builders** | Gate B before inventing Cloud UI |
| 7 | **WP8–9 Components + Variants** | Gate C local proof |
| 8 | **WP10 Connector** | Only after local runtime can consume a Manifest |
| 9 | **WP11–13 Decision Cloud + portal** | New control plane; keep react-cloud Google vault separate |
| 10 | **WP14–15b** | Events, Stripe, Paystack, then WP16 migration |
| 11 | **WP17–19** | Health/security/perf |
| 12 | **WP20** | AI advisory last (Optimise AI stays advisory locally until then) |

**Do not start WP12 portal before WP1–3.** Portal would invent a second language.

---

## 5. Technical risks (ranked)

| Rank | Risk | Impact | Mitigation |
|------|------|--------|------------|
| Critical | Visitor-path Cloud dependency introduced by mistake | Site outage / latency | Platform rule + Gate D offline test; code review checklist |
| Critical | Breaking portable rule storage | Customer content disappears | Alias layer; never rewrite stored JSON without migration version |
| High | Entitlement dual-stack bugs (standalone + Cloud) | Features unlock/lock incorrectly **or** covered plugins stay double-billed | Single grant-based `EntitlementProviderInterface`; commercial supersession per [PLAN.md](./PLAN.md) |
| High | Credential confusion (license / Google / site pairing) | Security + support load | Separate stores, labels, docs |
| High | Experiment re-assignment on Runtime cutover | Contaminated A/B results | Preserve cookie key or migrate map once |
| Medium | Elementor clone sharing slot IDs | Wrong experience on duplicated widgets | WP5/6 regeneration rules |
| Medium | Commerce outcome side effects outside Runtime | Cloud Experience can’t express full commerce | Capability actions wrap existing appliers |
| Medium | Performance of evaluating many Cloud Experiences | TTFB regression | WP19; lazy context; candidate filtering |
| Medium | Naming “Geo Core” vs “ReactWoo Core” | Docs/agent confusion | Architecture README terminology note |
| Low | WP Abilities premature adoption | Raised WP minimum | Optional adapter only (plan §5) |

---

## 6. Compatibility strategy (summary)

```text
Existing portable rule JSON ──alias──► Capability IDs
Existing RWGC_Rule_Evaluator ◄──adapter── Decision Runtime (phase 1)
Existing license checks ──facade──► EntitlementProvider
Existing Optimise assignment ──bridge──► Experiment assignment
Existing default Elementor/GB content ──fallback──► Variant failure
Local CPT/tables ──until WP16──► optional Cloud import (explicit)
```

Prefer **adapters and dual-read** over big-bang rewrites. Delete old engines only after Gate A (and for Optimise/Commerce, after product-specific regression suites) pass.

---

## 7. Immediate policy (effective now)

Until Cloud Decision Service exists:

1. New targeting inputs must be modellable as Core context/conditions (extend `RWGC_Rule_Evaluator` / Pro hooks), not a new satellite-only matcher.
2. New measurable outcomes should go through Optimise/Core event types where possible.
3. Do not add major features that assume a second independent rule engine inside Commerce or Optimise.
4. Atomic: continue Elementor product work, but treat Cloud component schema as a **separate contract** (WP8), not “ship all widgets to Cloud.”

---

## 8. WP0 gate

| Deliverable | Status |
|-------------|--------|
| `docs/architecture/current-state.md` | Done |
| `docs/architecture/cloud-migration-impact.md` | Done |
| No feature refactors in WP0 | Confirmed |
| Recommended order + risks | §4–§5 |

**Next:** WP1 — shared contracts (`reactwoo_schema_version = 1`) in Geo Core, without behaviour change.
