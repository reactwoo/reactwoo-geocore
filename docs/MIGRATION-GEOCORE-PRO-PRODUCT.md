# Migration plan: retire Geo Elementor, keep GeoCore + GeoCore Pro

**Status:** Phase 1 complete. **Phase 2 / 4 / 5 (partial)** shipped in GeoCore **1.8.3**, GeoCore Pro **0.1.41**, Geo Elementor shim **1.0.5.55**. Phases 3, 6–7 remain.  
**Last updated:** 2026-05-29

---

## Executive summary

| Plugin | Folder | Role |
|--------|--------|------|
| **GeoCore Free** | `reactwoo-geocore` | WordPress.org–compatible core. Geo engine, country targeting, shortcodes, basic routing, basic Elementor/Gutenberg, shared rule JSON and evaluator. |
| **GeoCore Pro** | `reactwoo-geocore-pro` | **Separate paid add-on** (stays its own plugin). Requires GeoCore Free. Unlocks advanced targeting, Google/GA4/Ads sync, profiles, weather, commercial licence. |
| **Geo Elementor** | `geo-elementor` | **Transitional only.** Not sold. Not required for new installs. Functionality merges into Free or Pro; plugin becomes a compatibility shim, then retires. |

**Do not** merge GeoCore Pro into GeoCore Free (org repo and licensing boundary).

**Do** merge Geo Elementor into:

- `reactwoo-geocore/includes/integrations/elementor/` — free baseline
- `reactwoo-geocore-pro/includes/integrations/elementor/` (and shared builder hooks) — premium

Gutenberg mirrors the same split under `includes/integrations/gutenberg/` in each repo.

---

## Correct product model

### GeoCore Free (`reactwoo-geocore`)

- MaxMind / country lookup, cache, REST, shortcodes
- `RWGC_Rule_Evaluator` and portable rule-set schema (evaluation is shared; **authoring** of advanced conditions is Pro-gated)
- `rwgc_visibility_rule` CPT (library storage; Pro unlocks full library UI + multi-condition apply)
- Basic Elementor: document-level country visibility (`includes/class-rwgc-elementor.php` today)
- Basic Gutenberg: `geo-content` block, country show/hide + portable evaluate when JSON present
- Page-variant routing (free tier limits documented in product)

### GeoCore Pro (`reactwoo-geocore-pro`)

- Licence slug: `reactwoo-geocore-pro` (ReactWoo Cloud + JWT)
- Filters: `rwgc_pro_enabled`, `rwgc_advanced_targeting_enabled` (`includes/class-rwgcp-bootstrap.php`)
- Google Ads / GA4 sync, experience profiles, weather, attribution context
- Advanced rule builder UI, library picker, premium condition types (device, UTM, audiences, page version, etc.)
- **Must** expose the same capabilities in Elementor **and** Gutenberg (UI may differ)

### Geo Elementor (`geo-elementor`) — transitional

- Legacy SKU and licence slug `geo-elementor`
- Element-level Elementor controls, `geo_rule` CPT admin, variant groups, popups, addons (city/time)
- `EGP_Geocore_Bridge` — temporary map of old licence → `rwgc_advanced_targeting_enabled`
- **Target:** compatibility shim only (detect Core/Pro, warn, prevent duplicate hooks, preserve meta)

---

## Feature ownership (target)

| Feature | GeoCore Free | GeoCore Pro |
|---------|:------------:|:-----------:|
| Geo detection engine | Yes | Extends if needed |
| MaxMind / country lookup | Yes | Extends if needed |
| Country targeting | Yes | Yes |
| Shortcodes | Yes | Yes |
| Basic routing | Yes | Yes |
| Basic Elementor controls | Yes | Yes |
| Basic Gutenberg block/support | Yes | Yes |
| Advanced rule builder | No | Yes |
| Multi-condition rules | No | Yes |
| Device / UTM / referral targeting | No | Yes |
| Page version targeting (advanced) | No | Yes |
| Saved visibility rule library | View/apply country-only | Full library |
| Variants / audience variant groups | No | Yes |
| GA4 audience targeting | No | Yes |
| Google Ads campaign targeting | No | Yes |
| Audience / entity sync | No | Yes |
| Premium Elementor controls | No | Yes |
| Premium Gutenberg controls | No | Yes |

---

## Builder parity rule

Any targeting capability must be available in **both** Elementor and Gutenberg when GeoCore Pro is active.

| Capability | Elementor (today) | Gutenberg (today) | Target |
|------------|-------------------|-------------------|--------|
| Country show/hide | Core doc + EGP elements | `geo-content` block | Both in **Free** |
| Document/page portable rules | Core doc controls (Pro-gated) | Post-level panel (`RWGC_Gutenberg_Post_Geo`) — **partial** | Both in **Pro** |
| Section/widget/container rules | Core `RWGC_Elementor_Elements` + EGP defer | **Gap** — no block wrapper | Both in **Pro** |
| Library picker | Core builder + EGP | Block editor context only | Both in **Pro** |
| GA4/Ads conditions in builder | Pro providers + schema | Same schema; UI parity | Both in **Pro** |
| Same JSON / evaluator | `RWGC_Rule_Evaluator` | Same | Unchanged |

---

## Phase 1 — Current-state audit (complete)

Legend: **P** = primary owner today, **S** = secondary/partial, **L** = legacy/duplicate, **—** = not present.

| Feature | GeoCore | GeoCore Pro | GeoElementor | Notes |
|---------|:-------:|:-----------:|:------------:|-------|
| Country targeting | **P** | S | S | Engine in Core (`RWGC_GeoIP`, countries API). EGP element controls call Core visitor country. |
| Elementor controls | S | S | **P** | Core: `RWGC_Elementor` document settings. EGP: section/widget/container + popups (`elementor-geo-popup.php`). |
| Gutenberg support | **P** | S | — | `RWGC_Gutenberg`, `blocks/geo-content`. Pro extends editor context via filters. |
| Rule builder (JS) | **P** | S | S | `assets/js/rwgc-rule-builder.js` in Core; gated by `rwgc_advanced_targeting_enabled`. EGP mounts on rules/elements. |
| Visibility rules library | **P** | S | L | CPT `rwgc_visibility_rule` in Core. EGP `geo_rule` CPT is parallel legacy. |
| Variants | S | — | **P** | Core: free page-variant routing (`RWGC_Routing`, `RWGC_Variant_Manager`). EGP: variant groups (`admin/variant-groups.php`). |
| GA4 audiences | S | **P** | — | `RWGC_Target_Provider_Analytics` + `RWGCP_Google_Integration`. |
| Google Ads audiences | S | **P** | — | `RWGCP_Google_Integration`, synced entities in Core targeting index. |
| Licence checks | — | **P** | L | Pro: `RWGCP_License`. EGP: `EGP_Licensing`, `geo-elementor` slug + `EGP_Geocore_Bridge`. |
| Frontend evaluator | **P** | S | S | `RWGC_Rule_Evaluator`. Pro adds context providers. EGP popup/element filters. |
| Shortcodes | **P** | — | S | Core shortcodes; EGP may register overlaps — audit on move. |
| Routing | **P** | — | S | Core `RWGC_Routing`. EGP optional `rwgc_route_variant_decision` extension (off by default). |

### Key files (audit anchors)

**GeoCore Free**

- `includes/class-rwgc-elementor.php` — document geo visibility + Pro portable section
- `includes/class-rwgc-gutenberg.php` — block + portable evaluate
- `includes/class-rwgc-targeting-rule-builder-assets.php` — shared builder
- `includes/targeting/class-rwgc-rule-evaluator.php` — single evaluator
- `includes/class-rwgc-visibility-rule-cpt.php` — library CPT
- `includes/class-rwgc-routing.php` — page variants (free)

**GeoCore Pro**

- `includes/class-rwgcp-bootstrap.php` — licence filters
- `includes/class-rwgcp-license.php` — commercial entitlement
- `includes/class-rwgcp-google-integration.php` — GA4/Ads sync
- `includes/class-rwgcp-admin.php` — setup, integrations, profiles
- `includes/class-rwgcp-portable-targeting.php` — Pro targeting helpers

**Geo Elementor (to absorb or shim)**

- `elementor-geo-popup.php` — element controls, frontend visibility
- `includes/geo-rules.php` — `geo_rule` CPT, admin UI
- `admin/variant-groups.php` — variant groups
- `includes/popup-hooks.php` — Elementor popup targeting
- `includes/licensing.php`, `includes/class-egp-geocore-bridge.php` — legacy licence
- `addons/city-targeting/`, `addons/time-targeting/` — decide Free vs Pro vs drop

### Phase 1 decisions (locked)

1. **Two-plugin model is final** — Free on org, Pro as commercial satellite (existing pattern with Geo AI / Commerce / Optimise).
2. **Geo Elementor is not a third product** — merge + shim only.
3. **No mass rename** of `EGP_*` until code is moved and tests pass; use wrappers/aliases during transition.
4. **Licence truth** — new premium gates use `rwgc_advanced_targeting_enabled` only; `egp_is_pro_user` is BC-only (already started in 1.8.2 / 0.1.40 / 1.0.5.54).

---

## Phase 2 — Move basic Elementor into GeoCore Free

**Goal:** New installs need only GeoCore Free for country-level Elementor visibility.

| Move from `geo-elementor` | To `reactwoo-geocore` |
|---------------------------|------------------------|
| Element-level country on/off (no portable builder) | `includes/integrations/elementor/class-rwgc-elementor-elements.php` |
| Popup country hooks (if country-only) | `includes/integrations/elementor/class-rwgc-elementor-popups.php` — **not yet** |
| Frontend visibility for country lists | `includes/integrations/elementor/class-rwgc-elementor-frontend.php` |
| Integrations loader + EGP defer filter | `includes/integrations/class-rwgc-integrations-loader.php` |

**Keep in EGP temporarily:** portable-heavy flows, `geo_rule` CPT admin/JS, variant groups, popups until Phase 3.

**Shipped (1.8.3):** `RWGC_Integrations_Loader`, element controls (`egp_*` keys), server-side `before_render` hide, rule builder enqueue in Elementor editor.

**Acceptance**

- [ ] Deactivate `geo-elementor`; country targeting still works on Elementor pages/sections/widgets via Core.
- [ ] No PHP fatal when Elementor Pro inactive (graceful admin notice only).

---

## Phase 3 — Move advanced Elementor into GeoCore Pro

**Goal:** Premium Elementor UX lives in Pro plugin; gates on licence.

| Move from `geo-elementor` | To `reactwoo-geocore-pro` |
|---------------------------|---------------------------|
| Portable rule builder mount on elements | `includes/integrations/elementor/` |
| `geo_rule` admin (or migrate to `rwgc_visibility_rule`) | Pro admin routes under app shell |
| Variant groups UI + routing extension | `includes/integrations/elementor/variants/` |
| GA4/Ads condition pickers in Elementor | Wire to existing `RWGCP_Google_Integration` entities |
| City/time addons (if still sold) | Pro or Free add-on modules — product decision per addon |

**Acceptance**

- [ ] With Pro licence: advanced Elementor controls match pre-migration behaviour.
- [ ] Without Pro: country-only controls only (Free).

---

## Phase 4 — Gutenberg parity

**Goal:** Every Pro Elementor capability has a Gutenberg equivalent.

| Deliverable | Location |
|-------------|----------|
| Post/page sidebar panel (portable rules) | `includes/integrations/gutenberg/class-rwgc-gutenberg-post-geo.php` + `assets/js/rwgc-post-geo-editor.js` — **partial** |
| Block wrapper advanced visibility | `blocks/geo-visibility` or extend `geo-content` — **not yet** |
| Library picker in block editor | Reuse `RWGC_Targeting_Rule_Builder_Assets` — **not yet** |
| Same condition list in editor context | `rwgc_get_portable_targeting_editor_context()` + Pro providers |

**Shipped (1.8.3):** Post-level country + portable meta, front-end `the_content` gate via `RWGC_Elementor_Frontend::settings_match_visitor()`.

**Acceptance**

- [ ] Side-by-side test: same rule JSON on Elementor page and Gutenberg post → same visitor outcome.
- [ ] Library apply works in both editors when Pro active.

---

## Phase 5 — Deprecate Geo Elementor

**Goal:** `geo-elementor` is optional shim, not a dependency.

1. **Shim plugin** (trimmed `geo-elementor`):
   - On load: if `RWGC_Plugin` active, show admin notice — functionality moved to GeoCore / GeoCore Pro. (**Shipped:** `includes/class-egp-migration-shim.php` in 1.0.5.55.)
   - Do **not** register Elementor hooks if Core integration classes exist. (**Shipped:** `rwgc_elementor_native_elements_active` guard in `elementor-geo-popup.php`.)
   - Read legacy options/meta only; no new features.
2. **Requires Plugins header:** change to recommend only `reactwoo-geocore` (+ `elementor`).
3. **Updates:** stop publishing new features; last shim releases only.
4. **Data:** no destructive migration; keep `egp_*` post meta and `geo_rule` posts readable from Core/Pro importers.

**Acceptance**

- [ ] Fresh install: GeoCore + Elementor works without `geo-elementor`.
- [ ] Existing site with shim: settings still apply; notice points to new screens.

---

## Phase 6 — Licence migration

| Item | Action |
|------|--------|
| New sales | SKU / slug `reactwoo-geocore-pro` only |
| Advanced targeting | `rwgc_pro_enabled` + `rwgc_advanced_targeting_enabled` from Pro bootstrap only |
| Legacy `geo-elementor` keys | Map server-side to Pro entitlement; keep `EGP_Geocore_Bridge` for 2–3 releases |
| Customer UI | Settings → GeoCore Pro (done in 0.1.40); no “Geo Elementor Pro” |
| New code | **Must not** call `egp_is_pro_user` or `geo-elementor` licence for gating |

**react-license / reactwoo-api / WHMCS:** map package `geo-elementor` → `reactwoo-geocore-pro` (see `reactwoo-api` env and publish endpoints).

---

## Phase 7 — Repo and folder strategy

```
reactwoo-geocore/                    # WordPress.org free
  includes/
    integrations/
      elementor/                     # Phase 2
      gutenberg/                     # Phase 4 (free parts)
    targeting/                       # shared evaluator (stays)
  blocks/

reactwoo-geocore-pro/                # Commercial (unchanged repo)
  includes/
    integrations/
      elementor/                     # Phase 3
      gutenberg/                     # Phase 4 (pro parts)

geo-elementor/                       # Shim only after Phase 5
  (minimal bootstrap + notices)
```

**Rules**

- Do **not** create a new commercial plugin from Geo Elementor.
- Do **not** rename `reactwoo-geocore-pro` folder.
- Prefer **move + class_alias / thin wrappers** over big-bang `EGP_` → `RWGC_` renames.
- R2 / CI slugs stay `reactwoo-geocore` and `reactwoo-geocore-pro`.

---

## Transitional work already shipped (reference)

| Release | Change |
|---------|--------|
| GeoCore 1.8.2 | Settings: GeoCore Pro tab; Elementor integration label; rule builder fixes; `rwgc_advanced_targeting_enabled`; library picker |
| GeoCore Pro 0.1.40 | Licence-aware Pro flags; Setup under Settings; legacy EGP licence redirect |
| Geo Elementor 1.0.5.54 | Pro gating via Core filters; licence UI deprecation; `EGP_Geocore_Bridge` |

---

## Acceptance criteria (migration complete)

- [ ] **Free:** GeoCore only (+ Elementor plugin) gives country targeting in Elementor and Gutenberg.
- [ ] **Pro:** GeoCore Pro unlocks advanced targeting in **both** builders.
- [ ] Geo Elementor **not required** for new installs.
- [ ] Geo Elementor **not sold**; no “GeoElementor Pro” in UI.
- [ ] Advanced targeting gated **only** by GeoCore Pro (plus documented legacy licence window).
- [ ] Elementor and Gutenberg **parity** for Pro features.
- [ ] Existing `egp_*` meta and `geo_rule` data still work (shim or importer).
- [ ] Legacy Geo Elementor Pro licences map to GeoCore Pro.
- [ ] **No destructive** data migration without backup and rollback plan.
- [ ] GeoCore Free remains suitable for **WordPress.org** (no commercial licence code in Free repo).

---

## Next implementation slice (recommended)

1. **Phase 2 spike:** Copy country-only element controls from `elementor-geo-popup.php` into `reactwoo-geocore/includes/integrations/elementor/` behind `RWGC_Elementor` bootstrap; feature-flag EGP element hooks off when Core class loaded.
2. **Phase 4 spike:** Gutenberg post-level meta panel (country + portable when Pro) — closes largest parity gap.
3. Release **GeoCore 1.8.3** + **GeoCore Pro 0.1.41** (setup step CTAs) as small incremental tags.

---

## Related docs

- `docs/geo-core-cursor-master-plan.md` — suite architecture
- `docs/releases-and-git-tags.md` — version tags
- `docs/GEO_SUITE_HOOKS.md` — integration hooks for satellites
