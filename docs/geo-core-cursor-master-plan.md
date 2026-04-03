# ReactWoo Geo Platform — Cursor Master Execution Plan

Single source of truth for direction, boundaries, and execution order. Supersedes earlier ad-hoc instructions if anything conflicts with this file.

---

## 1. Project context

The project is evolving from a geo-targeting setup into:

**Context-driven personalization and commerce optimization for WordPress**

**Primary codebases**

- **Geo Core** — Free (WordPress.org). Platform foundation and bridge. Today: page-wide rules, popup targeting, legacy **main / alternate** country routing (being replaced internally; see §3). Local plugin folder: **`reactwoo-geocore/`**.
- **GeoElementor** — Pro-facing Elementor integration. Still holds overlapping logic to migrate into Geo Core over time.
- **Geo AI** — **Separate plugin** (own repo, own release cadence). AI-assisted drafts and optimization UI/workflows; consumes Geo Core + ReactWoo API. **Not** folded into Geo Core as a monolith. Scaffold: **`reactwoo-geo-ai/`** (`rwga_loaded`).
- **Geo Commerce (Woo)** — **Separate plugin**. WooCommerce overlays, pricing rules, attribution. **Independent** from Geo Core and from Geo AI. Scaffold: **`reactwoo-geo-commerce/`** (`rwgcm_loaded`; requires WooCommerce).
- **Geo Optimise** — **Separate plugin** (own repo). Optimization / experimentation workflows that sit on top of the geo platform. **Requires Geo Core** for all visitor geo (detection, context, routing helpers) — linked product, not a second geo engine. Scaffold: **`reactwoo-geo-optimise/`** (`rwgo_loaded`).

---

## 2. Strategic direction

| Product | Role |
|--------|------|
| **Geo Core (Free)** | Context + rules + variants + fallback engine |
| **GeoElementor (Pro)** | Elementor UX, controls, preview |
| **Geo Commerce (Pro)** | WooCommerce overlays, pricing, attribution |
| **Geo AI (Subscription)** | Generation and optimization **via ReactWoo API** (not direct vendor SDKs in plugins) |
| **Geo Optimise** | Separate plugin: CRO / tuning / optimization flows — **uses Geo Core** for every geo concern |

**Product split:** **Geo AI**, **Geo Commerce**, and **Geo Optimise** are **independent WordPress plugins** (own roadmaps and releases). They are **linked** to the same platform: they **require Geo Core** for geo functions and should integrate via hooks, REST, and public PHP APIs — **not** duplicate detection or MaxMind inside those plugins. Geo Core stays the free engine; satellite plugins add their own UI and product logic **without** Geo Core becoming an all-in-one bundle.

---

## 3. Core architectural shift

**Replace:** main country + alternate country as the long-term internal model.

**With:** default content, **multiple** variants, rule-based conditions, explicit fallback order.

Example: default = global; variants = UK, US, ZA; fallback = default.

Implement this **internally** (with adapters) before layering new product features on top.

---

## 4. Non-negotiable rules

1. **Geo Core** owns: context detection, rules, variants, fallback, shared event foundations.
2. **GeoElementor** becomes a **consumer** of Geo Core services, not the long-term owner of platform targeting logic.
3. **AI** produces **draft variants only** — no silent publish of live content.
4. **WooCommerce:** do not duplicate products for personalization; use overlays / rules.
5. **Pricing** is deterministic and rule-based, not AI-controlled.
6. **Backward compatibility** during migration; no big-bang rewrite.
7. **Incremental** delivery; each phase shippable.
8. **Do not** build AI on the legacy two-branch (main/alternate) model.
9. **Free vs Pro** boundaries stay explicit (see §8).
10. **ReactWoo infrastructure:** AI, licensing, and usage go through the official API and license stack (see §6) — no parallel systems.
11. **Configurable targeting** uses **selectable, prepopulated controls** — not CSV or free-typed country lists in user-facing UIs (see §5.1).
12. **WordPress.org Geo Core** must not require a ReactWoo product license for detection, routing, shortcodes, block, REST location, or other core geo features. Optional ReactWoo credentials are **only** for AI / API add-ons.
13. **Geo AI**, **Geo Commerce (Woo)**, and **Geo Optimise** are **separate plugins** — own codebases, roadmaps, and releases. Do **not** implement full AI product UI, full Woo overlays, or full optimization product UI **inside** Geo Core; keep thin hooks, REST bridges, and filters here so those plugins can integrate cleanly.
14. **Satellite plugins depend on Geo Core for geo:** AI, Commerce, Optimise (and similar) **must** use Geo Core for visitor geo — `rwgc_*` / `RWGC_*` APIs, filters, and routing contracts. Do **not** fork a parallel MaxMind stack or duplicate country resolution in those plugins.

---

## 5. UI consistency (GeoElementor is canonical)

GeoElementor defines the reference for **layout, spacing, typography, controls, modals, and interaction patterns**.

All ReactWoo geo-related plugins (Geo Core, GeoElementor, Geo Commerce, Geo AI, Geo Optimise) **align with** those patterns.

- Do **not** introduce a separate admin design system.
- If a pattern is missing, **extend** GeoElementor-style patterns; extract shared components over time.

Longer-term: optional shared UI component layer across plugins (implementation detail — still visually consistent with GeoElementor).

### 5.1 Selectable inputs — no CSV as primary configuration

**Product rule (all ReactWoo geo plugins):** End users must **not** rely on typing comma-separated values (CSV), raw ISO2 strings, or ad-hoc slug lists in text fields to configure targeting, rules, or variants.

- **Admin, Gutenberg, Elementor, and settings UIs** must use **prepopulated, selectable controls**: country search / multi-select against the canonical list, pickers for registered country groups, toggles, and other GeoElementor-style controls — never a plain text box where users type `US,CA,GB`.
- **Internal persistence** may still use arrays, JSON, or serialized meta; that is implementation detail. Users do not edit those as CSV.
- **Legacy:** Shortcodes or older attributes that parse comma-separated codes may exist for backward compatibility; **new features** must use structured UIs. Documentation and onboarding should point authors to the block and editor controls. Phased migration off CSV-style shortcode entry is expected.

---

## 6. Platform infrastructure

**Required integration**

| Service | Base URL | Purpose |
|--------|----------|---------|
| **API** | `https://api.reactwoo.com` | AI and server-backed features, usage, gating where applicable |
| **License** | `https://license.reactwoo.com` | License validation, plans, tokens for API use |

**Rules**

- Validate licenses via the license server; use issued tokens for API calls as documented by ReactWoo.
- **Do not** embed direct OpenAI (or other vendor) calls in plugins for product AI — orchestration stays in plugins; generation/quotas live behind the API.
- **Do not** invent a second licensing or usage-tracking stack inside plugins.

**Plans** (from license/API contract) may expose features, quotas, and flags; plugins adapt behavior accordingly.

**Plugins** are WordPress-side orchestration and UX; heavy or metered AI work goes through the API.

---

## 7. Data model direction

Conceptual shapes (full schemas evolve by phase):

- **Context** — e.g. country, device, language, campaign, user type (extensible).
- **Rule** — type, priority, `conditions_json`, scheduling as needed later.
- **Variant** — type, `payload_json`, source reference (overlay, not duplicate entities).
- **Event** — e.g. impression, click, purchase (for experiments and commerce later).

---

## 8. Free vs paid boundaries

**Geo Core (WordPress.org free)** — Detection, page + popup targeting, multi-country direction, fallback, shortcode, Gutenberg block, basic Elementor bridge, preview foundations. **Does not require a ReactWoo product license.** MaxMind GeoLite credentials are third-party; an optional ReactWoo product key is **only** for add-on AI API access, not for core geo.

**GeoElementor (Pro)** — Advanced controls, richer variant UX, advanced preview.

**Geo Commerce (Pro)** — **Separate plugin.** Product experience overlays, pricing/currency/discount rules, attribution (Woo).

**Geo AI** — **Separate plugin.** Rewrite, translate, generate draft variants, optimization — **via API**, with usage limits per plan. Ships its own screens and workflows; Geo Core may expose minimal orchestration endpoints or filters only.

**Geo Optimise** — **Separate plugin.** Linked to the platform: **requires Geo Core** for detection, context, and routing; owns optimization/CRO/experimentation UX and logic that *consumes* geo from Core (no duplicate geo engine).

---

## 9. Implementation philosophy

- Migrate **internals** first; keep legacy behavior working behind adapters.
- Ship **incrementally**; document migrations and compatibility each phase.

---

## 10. Execution phases

Phases below describe **capability areas** in the platform narrative. **Geo AI** (Phase 5), **Geo Commerce** (Phase 7), and **Geo Optimise** (cross-cutting / own plugin) are built primarily **outside** Geo Core; Geo Core work in those areas should stay **contract-level** (hooks, REST, data shapes, stable `rwgc_*` APIs) unless a change is explicitly scoped to the free engine. Satellite plugins **depend on Core** for geo (see §4.14).

| Phase | Focus |
|-------|--------|
| **1 — Core refactor** | Rules + variants + fallback services; legacy adapter from main/alternate |
| **2 — Multi-country** | Multiple rules, includes/excludes, groups |
| **3 — Free UX** | Block, shortcode, preview polish — **picker-based admin/block (§5.1)** |
| **4 — Elementor** | GeoElementor consumes Geo Core; thin integration; **no CSV targeting inputs (§5.1)** — see `docs/phases/phase-4.md` |
| **5 — AI layer** | API-based generation; drafts and review flow |
| **6 — Experiments** | A/B, assignment, events |
| **7 — Geo Commerce** | Woo overlays, pricing rules, attribution |

**Geo Core (this repo) — where phases stand:** Phases **1–4** are implemented in the engine and admin UX. **Phase 5 (AI):** Core ships a thin REST + platform client + Tools checks; full AI product UI lives in the **Geo AI** plugin. **Phase 6 (experiments):** Core ships `RWGC_Event`, `rwgc_geo_event`, route-redirect emission, and REST **`/capabilities`** (including integration hook lists and `woocommerce_active`); full A/B and CRO UI live in **Geo Optimise**. **Phase 7 (commerce):** Core exposes Woo discovery helpers only; Woo overlays live in the **Geo Commerce** plugin. See `docs/phases/`.

Follow phases **in order** unless a later task explicitly depends only on an earlier deliverable (still no skipping wholesale phases).

---

## 11. Cursor agent model (workstreams)

Use parallel workstreams where helpful; one phase owner still gates merge.

- **Architect** — Boundaries, contracts, migration order.
- **Geo Core engine** — Context, rules, variants, fallback, adapters.
- **Geo Core UX** — Admin, blocks, shortcode, preview (GeoElementor-aligned).
- **Elementor** — Controls and bridge only.
- **AI** — **Geo AI plugin** codebase: API client, jobs, editor UX, drafts — **no direct vendor AI in-plugin for product features**; Geo Core only thin/shared pieces.
- **Experiments** — Events, assignment, reporting hooks.
- **Woo** — **Geo Commerce plugin** codebase: overlays, pricing resolver, attribution — not Geo Core.
- **Optimise** — **Geo Optimise plugin** codebase: optimization/CRO flows — **consumes Geo Core** for geo; not Geo Core.
- **QA** — Regression, migration safety, security.

**Workflow:** Prefer **one continuous dev thread** (see **`docs/AGENTS.md`**). Phase history lives in **`docs/phases/`**.

---

## 12. Parallel streams

- Data & migration  
- Engine services  
- UI & integration  
- Monetization / API hook points (without blurring free vs paid)

---

## 13. Folder structure (target)

**Geo Core** — `includes/` with logical areas such as:

- `context/`, `rules/`, `variants/`, `migration/`, `events/`, plus `rest/`, `admin/`, `blocks/` as needed.

Interim layouts (e.g. `includes/engine/` for shared classes) are acceptable while refactoring **toward** this layout; avoid permanent duplicate “second systems.”

**GeoElementor** — `includes/integration/`, `includes/admin/` (and existing addon layout as today).

---

## 14. Cursor execution rules

- Treat **this file** as authoritative when it conflicts with chat snippets or old prompts.
- Run phases **sequentially**; preserve backward compatibility.
- No full-platform rewrite in one PR.
- At **end of each phase**, record: architecture decisions, files changed, migration notes, compatibility notes, risks, recommended next phase.
- Prefer **advancing the plan** (next backlog item, tests, docs) over asking the user to “continue.” Full stack includes **admin UX** in satellites unless the user explicitly scopes a task down (see **`docs/AGENTS.md`**).

---

## 15. Starting instruction (for Cursor)

Read this document in full before large changes.

This is a **refactor** of existing Geo Core and GeoElementor codebases, not a greenfield rewrite.

**Must-haves**

- Move from legacy main/alternate thinking to **default + variants + conditions + fallback** in the engine, with adapters until legacy storage is retired.
- Preserve existing site behavior unless a phase explicitly ships a breaking change with migration.
- Use **ReactWoo API and license.reactwoo.com** for AI, licensing, and centralized usage — not ad-hoc alternatives.
- Match **GeoElementor** admin UX patterns for any new Geo-related UI.
- **No CSV-style configuration in UIs** — selectors and prepopulated lists only (§5.1).

**Start at Phase 1** (see §10 — core refactor): architecture notes, migration layer, rule + variant + fallback scaffolding — then continue in order.

Do not skip phases arbitrarily; do not rewrite the entire codebase at once.

---

## 16. Execution status (Geo Core + satellites)

**Geo Core (this repo)** — Phases **1–7** are **addressed in code and contracts** for the free engine: refactor through routing, UX (§5.1), Elementor bridge notes, optional AI REST/orchestrator (no Core license gate for core geo), events + REST `/capabilities`, Woo discovery helpers, and documentation. Ongoing product work for **AI / Optimise / Commerce** lives in **satellite plugins** (separate folders), not by expanding Geo Core into an all-in-one.

**Satellite implementation order (product)** — **Geo AI** → **Geo Optimise** → **Geo Commerce**, each under `wp-content/plugins/` (`reactwoo-geo-ai`, `reactwoo-geo-optimise`, `reactwoo-geo-commerce`). See **`docs/AGENTS.md`**.

---

## 17. Satellite depth shipped (rolling)

Implemented outside Geo Core (separate plugins), aligned with §5.1 (selectable admin inputs) and §4 (no duplicate geo engines):

| Stream | Delivered (representative) |
|--------|----------------------------|
| **Geo Commerce** | `rwgc_geo_data` cart hint; **Commerce pricing** (simple + **variable** storefront); **cart fees** + **tax class**; **orders list** visitor country (**column + sort**); admin **REST capabilities**; `rwgcm_before_cart_totals`, `rwgcm_adjusted_unit_price`. |
| **Geo Optimise** | Event bridge + counters + CSV export (**telemetry**, ratio **assignment / route**, **`rwgo_export_csv_filename`**, host in filename); **`rwgo_get_variant()`** (sticky cookie); **`rwgo_variant_assigned`**; assignment distribution in stats. |
| **Geo AI** | Dashboard + **REST v1 base** + **capabilities** + **location** + local **variant-draft** validation POST + connection summary + API health/usage; **`rwga_stats_snapshot`**; **block editor** sidebar (variant-draft REST URL, copy + open tab + JS i18n). |
| **Geo Core** | REST **`satellites`** + curated **`integration.satellite_*`** hook names; bump readme when satellites add filters. |

## 18. Next wave (backlog)

| Stream | Direction |
|--------|-----------|
| **Geo Commerce** | Third-party bundle/composite plugins — **`rwgcm_skip_pricing_for_cart_item`** (extend as product needs). |
| **Geo Optimise** | Optional **funnel** steps (route → assignment → conversion) if product needs it; **CSV** + snapshot telemetry shipped. |
| **Geo AI** | Deeper **block** integrations optional. |
| **Geo Core** | No mega-features; keep REST **`integration.*`** and `docs/AGENTS.md` in sync when satellites ship. |

Order for **implementation** when stacking tickets: **AI → Optimise → Commerce** unless a task depends only on Core.
