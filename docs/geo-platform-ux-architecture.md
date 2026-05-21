# ReactWoo Geo Platform — UX & Product Architecture (Final)

**Status:** Target architecture (authoritative for UX/navigation; engine boundaries remain in `geo-core-cursor-master-plan.md`).

**Principle:** Users operate **ReactWoo Geo** (one platform). Satellites are **capability providers** — commercially separate, technically modular, UX-invisible as navigation roots.

---

## 1. Strategic alignment

| Layer | Role |
|-------|------|
| **ReactWoo Geo** (Geo Core hub) | Platform shell, routing registry, shared admin framework, targeting engine contracts |
| **GeoCore Pro** | Premium capabilities (Google, weather, campaign/audience targeting providers) — not a primary nav destination |
| **Geo Commerce / AI / Optimise / Elementor** | Feature packs; register routes and UI fragments; no standalone mental model |

**Commercial names (UI):** Geo Commerce, Geo AI, Geo Optimise, Geo Elementor  
**Plugin headers / licensing:** ReactWoo Geo Commerce, ReactWoo Geo AI, etc.

---

## 2. Current codebase baseline (as of audit)

### Already implemented (Geo Core)

| Component | Location | Notes |
|-----------|----------|-------|
| Single hub parent | `RWGC_Admin_Platform` | Top-level **ReactWoo Geo** (`rwgc-dashboard`) |
| Hidden flyout support | `collapse_hub_submenu()` | Filter `rwgc_admin_sidebar_collapsed` — **default `true`**; Setup wizard kept via `rwgc_admin_visible_submenu_slugs` |
| App shell frame | `RWGC_Admin_App_Shell` | Six **goal sections** + contextual tabs; filter `rwgc_app_shell_render` — **default `true`** |
| Route registry | `RWGC_Admin_Route_Registry` | `section` + `provider`; Core + satellites register via `rw_geo_register_app_route()` |
| Public API | `rw_geo_register_app_route()`, `rw_geo_app_url()`, `rwgc_uses_platform_shell()` | `functions-rwgc.php` |
| Shared UI primitives | `RWGC_Admin_UI`, `rwgc-suite.css` | Cards, pills, satellite grid |
| Suite onboarding (MVP) | `RWGC_Suite_Admin`, `RWGC_Onboarding` | Fragmented vs unified wizard target |
| Portable targeting engine | `RWGC_Targeting_Rule_Set_Evaluator` | JSON schema; visual builder = follow-up |

### Satellite behaviour today

- **Geo AI, Commerce, Optimise, Elementor:** Register hub routes with `section` + `provider`; legacy inner nav skipped when `rwgc_uses_platform_shell()`.
- **Geo Elementor:** Hub parent when Core active; legacy top-level when not.
- **GeoCore Pro:** In-shell horizontal tabs via `rwgc_app_shell_context_links`; duplicate **Settings → GeoCore Pro** menu removed.

### Drift vs target

| Target | Current |
|--------|---------|
| WP sidebar: ReactWoo Geo only | Many hub submenus visible; Pro also under Settings |
| In-app nav: 6 goal areas | Module-centric shell (Core / Pro / AI / …) |
| Optimise under Targeting | Optimise is a module + own primary workflows |
| AI under Insights | AI is a module + own Reports/Recommendations nav |
| Shared upgrade UX | `RWGC_Admin_UI::render_upgrade_card()` for capability gaps |
| GeoCore Pro design reference | Master plan still cites Geo Elementor as canonical UI — platform doc uses **GeoCore Pro** CSS/shell |

---

## 3. Target information architecture

### WordPress sidebar (entry only)

```
ReactWoo Geo          → admin.php?page=rwgc-dashboard (Overview)
Setup Wizard (opt.)   → rwgc-getting-started (hidden from flyout when collapsed)
```

**Filters:** `rwgc_admin_sidebar_collapsed` = true; `rwgc_admin_visible_submenu_slugs` = optional wizard slug only.

### App shell — primary navigation (6 max)

| # | Section ID | Label | Purpose |
|---|------------|-------|---------|
| 1 | `overview` | Overview | Health, sync, onboarding, quick actions, alerts, usage |
| 2 | `targeting` | Targeting | Rules, experiences, audiences, campaigns, variants, scheduling, experiments |
| 3 | `commerce` | Commerce | Woo pricing, visibility, cart, overlays, attribution |
| 4 | `insights` | Insights | Reports, AI recommendations, analytics, experiment outcomes |
| 5 | `integrations` | Integrations | GA4, Ads, Meta, Woo, APIs, licences, sync |
| 6 | `settings` | Settings | General, Geo DB, performance, roles, advanced, developer |

**Rename registry dimension:** `module` → `section` (keep `module` as deprecated alias for one release).

### Contextual secondary navigation

Registered per section via `rw_geo_register_app_route( [ 'section' => 'targeting', 'route' => 'rules', ... ] )`.

**Targeting tabs:** Rules · Experiences · Audiences · Campaigns · Variants · Scheduling · Experiments  
**Commerce tabs:** Pricing · Products · Cart Rules · Overlays · Attribution  
**Insights tabs:** Overview · Geo · Audiences · Campaigns · Commerce · Experiments · AI (capability-gated)  
**Integrations tabs:** Connections · Sync · Licences · Data sources  
**Settings tabs:** General · Geo database · Performance · Roles · Advanced · Developer · Logging

### Topbar (shell chrome)

Search · Notifications · Sync status · Active integrations · Help · User — implemented as `RWGC_Admin_App_Shell` partials (new) or shared `reactwoo-geocore/admin/js/` components.

---

## 4. Route registration contract (evolved)

### Canonical API

```php
rw_geo_register_app_route( [
    'section'      => 'commerce',           // required: overview|targeting|commerce|insights|integrations|settings
    'route'        => 'pricing',            // slug within section
    'menu_slug'    => 'rwgcm-pricing',      // wp-admin page (stable URLs)
    'label'        => __( 'Regional Pricing', 'reactwoo-geo-commerce' ),
    'page_title'   => __( 'Regional Pricing', 'reactwoo-geo-commerce' ),
    'callback'     => [ 'RWGCM_Admin', 'render_pricing' ],
    'order'        => 10,
    'capability'   => 'manage_woocommerce',
    'capability_required' => 'geo_commerce', // optional: licence/feature flag
    'provider'     => 'geo_commerce',        // for “Powered by” badge
    'show_in_wp_sidebar' => false,           // default
] );
```

### Registry changes (`RWGC_Admin_Route_Registry`)

1. Replace `register_default_modules()` with `register_default_sections()` (6 rows).
2. `get_routes_for_section( $section )` drives horizontal tabs.
3. `get_current_context()` returns `section`, `route`, `menu_slug`, `label`, `provider`.
4. Filters: `rwgc_app_sections`, `rwgc_app_routes` (backward-compat: `rwgc_app_modules`).
5. Deprecate module inference from slug prefixes for **new** routes; keep inference for legacy URLs during migration.

### URL helper

`rw_geo_app_url( 'targeting', 'rules' )` → resolves first matching `menu_slug` or explicit slug map.

---

## 5. Satellite migration map

Routes move from plugin-named hubs to **section + route**. Existing `?page=` slugs stay for bookmarks.

| Current slug / area | Target section | Target route | Provider badge |
|---------------------|----------------|--------------|----------------|
| `rwgc-dashboard`, suite home | overview | dashboard / suite | core |
| `rwgc-getting-started` | overview | setup | core |
| `rwgc-suite-variants`, target types, Elementor rules | targeting | rules / experiences / … | core / elementor |
| `rwgo-*` experiments, tests | targeting | experiments | geo_optimise |
| `rwgcm-*` pricing, overlays | commerce | * | geo_commerce |
| `rwgc-usage`, `rwga-analyses`, optimise reports | insights | * | core / geo_ai / geo_optimise |
| `rwgcp-*` Google, weather, profiles | integrations | * | geocore_pro |
| `rwgc-settings`, satellite license pages | settings | * | per plugin |

**Per satellite tasks:**

1. Replace `add_submenu_page` loops with `rw_geo_register_app_route` batches on `rwgc_loaded` (priority 15–25).
2. Remove `hide_detail_submenu_css` hacks once flyout collapsed globally.
3. Replace `render_inner_nav()` with registry-driven section tabs (shell renders).
4. Add `provider` meta to page headers: “Powered by Geo AI” via `RWGC_Admin_UI::render_provider_badge()`.

**Geo Elementor:** Remain editor-native for widget controls; **admin screens** only register under `targeting` + `integrations` (license). No top-level menu when Core active (already mostly true).

**GeoCore Pro:** Fold vertical nav into shell; remove `options-general.php` duplicate submenu; migrate Google/weather to `integrations`.

---

## 6. Capability & upsell UX

### Capability model (extend REST + PHP)

`GET …/v1/capabilities` already exposes `satellites.*.ready`. Add:

```json
"features": {
  "ai_recommendations": { "ready": true, "provider": "geo_ai" },
  "experiments": { "ready": false, "provider": "geo_optimise", "upgrade_url": "..." }
}
```

### Upgrade cards (not dead nav)

When `capability_required` fails: render `RWGC_Admin_UI::upgrade_card()` on route callback stub — never register broken menu items in WP sidebar.

---

## 7. Targeting UX (Phase 2)

**Mental model:** “Building experiences” — one workflow surface.

| Work item | Owner |
|-----------|-------|
| Visual rule builder (AND/OR, groups, exclusions, human summary) | Geo Core admin + shared JS |
| Hide raw JSON by default; Advanced → collapsible | All rule UIs |
| Synced audience/campaign/country selectors | Core + Pro providers |
| Experiments UI relocated under Targeting → Experiments | Geo Optimise registers routes only |
| Portable schema unchanged | `RWGC_Targeting_Rule_Set_*` |

See `docs/TARGETING-RULES-PLAN.md` — visual builder is explicit follow-up.

---

## 8. Design system

**Reference:** GeoCore Pro admin CSS (`reactwoo-geocore-pro/assets/admin/css/geocore-pro-admin.css`) for density, cards, nav — **extract tokens** into `reactwoo-geocore/admin/css/rwgc-design-system.css` (variables: spacing, radius, type scale, surfaces).

**Shared components (PHP + CSS BEM prefix `rwgc-`):**

| Component | Responsibility |
|-----------|----------------|
| App shell layout | `RWGC_Admin_App_Shell` |
| Topbar | `RWGC_Admin_Topbar` (new) |
| Section tabs | shell `__section-nav` |
| DashboardCard / StatusCard | `RWGC_Admin_UI` |
| RuleBuilder | JS module `rwgc-rule-builder` (Phase 2) |
| Selectors | audience, campaign, country |
| EmptyState, SyncState, DependencyNotice, UpgradeCard, Modal, DataTable | `RWGC_Admin_UI` extensions |

Geo Elementor editor controls remain Elementor-native; admin lists align with extracted tokens.

---

## 9. Unified onboarding

Replace fragmented suite + per-plugin wizards with **one** flow under `overview → setup`:

1. Welcome  
2. Geo Database  
3. Google Connections (Pro capability)  
4. WooCommerce  
5. Audience Sync  
6. Campaign Sync  
7. Feature Enablement (capabilities)  
8. Create First Experience  

Implementation: extend `RWGC_Onboarding` state machine; satellites hook `rwgc_onboarding_step_*` instead of separate redirects.

---

## 10. Phased implementation plan

### Phase 1 — Platform shell (4–6 weeks)

**Goal:** One WP menu item; goal-based in-app nav; shell enabled.

| Step | Repo | Work |
|------|------|------|
| 1.1 | geocore | Default `rwgc_admin_sidebar_collapsed` → true (or filter in bootstrap) |
| 1.2 | geocore | Default `rwgc_app_shell_render` → true on hub screens |
| 1.3 | geocore | Refactor `RWGC_Admin_Route_Registry` → sections (6) |
| 1.4 | geocore | Refactor `RWGC_Admin_App_Shell` → section nav + topbar stub |
| 1.5 | geocore | Migrate Core menus to `rw_geo_register_app_route` |
| 1.6 | satellites | Batch-register routes; remove inner nav CSS hacks |
| 1.7 | geocore-pro | Disable standalone Pro nav; register under integrations/settings |
| 1.8 | geo-elementor | Confirm no top-level menu when Core active |

**Exit criteria:** WP sidebar shows only ReactWoo Geo (+ optional Setup); all admin URLs render inside shell; no duplicate horizontal nav bars.

### Phase 2 — Unified targeting (6–8 weeks)

- Experience builder routes under `targeting`
- Visual rule builder; JSON advanced-only
- Optimise experiment screens → `targeting/experiments`
- Elementor portable controls use shared selectors

### Phase 3 — Consolidated surfaces (4–6 weeks)

- **Insights:** merge reports (Core usage, AI analyses, Optimise outcomes)
- **Integrations:** Pro Google, licences, sync status
- **Settings:** single grouped settings API; satellite sections as tabs

### Phase 4 — Polish (ongoing)

- Responsive nav (drawer / top tabs) — partial (`rwgc-app-shell.css` breakpoints)
- AI recommendations in Insights only — routes under Insights section
- Onboarding redesign — platform checklist on Overview + setup wizard retained
- Provider badges + upgrade cards — `render_provider_badge()`, `render_upgrade_card()`, hub cards

**Shipped in 1.5.0:** Phases 1–3 core deliverables (shell, route API, hubs, sync topbar, targeting labels, design tokens).

---

## 11. Technical risks

| Risk | Mitigation |
|------|------------|
| Bookmarked `?page=rwga-*` URLs | Keep `menu_slug` stable; only nav grouping changes |
| Shop manager caps | Preserve `rwgc_required_capability` / per-route `capability` |
| Pro shell CSS conflicts | Load order: design-system → suite → shell → page |
| Geo Elementor without Core | Legacy top-level remains; document unsupported UX |
| WordPress.org Core scope | Free features stay in Core sections; licence gates via capability cards |
| Module filter consumers | Deprecate `rwgc_app_modules`; alias to sections for 2 releases |

---

## 12. Test checklist (Phase 1)

- [ ] WP admin: only **ReactWoo Geo** visible (and Setup if enabled in `rwgc_admin_visible_submenu_slugs`)
- [ ] Direct URL `?page=rwga-dashboard` loads inside shell with **Insights** or mapped section active
- [ ] Section tabs switch without PHP notices; active state correct
- [ ] `manage_woocommerce` user sees Commerce routes; denied routes show upgrade card
- [ ] GeoCore Pro Google screen under Integrations; no duplicate Settings menu
- [ ] Geo Elementor with Core: no second top-level menu
- [ ] Mobile: section nav scroll / drawer (shell JS)
- [ ] Deactivating satellite removes section tabs; Overview still loads

---

## 13. Documentation & governance

- **Engine / API boundaries:** `geo-core-cursor-master-plan.md` (unchanged)
- **This file:** UX/navigation/product IA
- **Hooks:** extend `docs/GEO_SUITE_HOOKS.md` with `rwgc_app_sections`, route registration examples
- **Agents:** update `docs/AGENTS.md` — new screens must use `rw_geo_register_app_route`, not `add_submenu_page` directly

---

## 14. Implementation status (Phase 1)

**Done:**

1. `RWGC_Admin_Route_Registry` — six goal `section`s + slug inference.  
2. App shell + collapsed WP sidebar enabled by default.  
3. `RWGC_Admin_UI::render_provider_badge()`.  
4. Satellites migrated to `rw_geo_register_app_route` (or hub wrapper): **Geo Commerce**, **Geo AI** (Insights), **Geo Optimise** (Targeting + Insights reports), **GeoCore Pro** (Integrations; legacy `rwgcp-settings` redirects), **Geo Elementor** (Targeting via central `register_hub_submenu`).

**Next (Phase 2+):**

- Migrate Core `RWGC_Admin::register_menu()` to explicit route registration (optional; inference works today).  
- Wire topbar **Sync** pill to real integration health (filter `rwgc_app_shell_sync_label`).  
- Unified onboarding wizard; visual rule builder (Phase 2).

**Phase 1 shell (latest):**

- GeoCore Pro renders inside the shared shell; vertical `rwgcp-sidebar` hidden; Pro tabs via `rwgc_app_shell_context_links`.  
- Topbar stub: section title, Sync, Help, user + `rwgc_app_shell_topbar` action.
