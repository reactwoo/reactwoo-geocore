# Geo Admin IA — implementation checklist

Intent model: **Targeting = WHO**, **Experiences = WHAT + TEST**, **Commerce = SELL**, **Insights = MEASURE**, **Integrations = CONNECT**, **Settings = CONFIGURE**.

## Top-level sections

| Section | Status | Hub slug |
|---------|--------|----------|
| Overview | Done | `rwgc-dashboard` |
| Targeting | Done | `rwgc-targeting-hub` |
| Experiences | Done | `rwgc-experiences-hub` |
| Commerce | Done | `rwgc-commerce-hub` |
| Insights | Done | `rwgc-insights-hub` |
| Integrations | Done | `rwgc-integrations-hub` |
| Settings | Done | `rwgc-settings-hub` |

## Targeting (visible section nav)

| Item | Status | Slug / notes |
|------|--------|--------------|
| Overview | Done | `rwgc-targeting-hub` |
| Rules | Done | `rwgc-visibility-rules` |
| Audiences | Done (entry) | `rwgc-targeting-audiences` → Pro profiles |
| Campaigns | Done (entry) | `rwgc-targeting-campaigns` → Google + campaign insights |
| Geo conditions | Done | `rwgc-target-types` (was Rule builder) |

**Hidden from primary nav:** edit screens, playground-only flows, internal provider tools (`is_section_nav = false`).

## Experiences

| Item | Status | Slug / notes |
|------|--------|--------------|
| Overview | Done | `rwgc-experiences-hub` |
| Variants | Done | `rwgc-suite-variants` |
| Dynamic content | Done | `geo-elementor-rules` |
| Geo content | Done | `geo-content` |
| Experiments | Done | `rwgo-dashboard` (Optimise) |
| Reports | Done | `rwgo-reports` (Optimise) |

**Hidden:** `rwgo-create-test`, `rwgo-tests`, `geo-elementor-variants` (Groups).

## Commerce

| Item | Status | Slug / notes |
|------|--------|--------------|
| Overview | Done | `rwgc-commerce-hub` |
| Pricing rules | Done | `rwgcm-pricing` |
| Product overlays | Done | `rwgcm-product-overlays` |
| Offers | Done | `rwgcm-fees` |
| Merchandising | Done (entry) | `rwgcm-merchandising` |
| Availability | Done (entry) | `rwgcm-availability` |

**Insights (not Commerce nav):** `rwgcm-attribution` → Commerce performance.

**Settings (not Commerce nav):** diagnostics, license, commerce settings, help.

## Insights

| Item | Status | Slug / notes |
|------|--------|--------------|
| Overview | Done | `rwgc-insights-hub` |
| Geo insights | Done | `rwgc-usage` |
| Audience insights | Done (mode) | `rwgc-usage-audience` |
| Campaign insights | Done (mode) | `rwgc-usage-campaign` |
| Experience performance | Done (entry) | `rwgc-insights-experiments` → Optimise reports |
| Commerce performance | Done | `rwgcm-attribution` (when Commerce active) |

## Integrations

Categories (shell subnav): **Analytics · Advertising · APIs · Ecommerce · Content builders · System services** (`RWGC_Admin_Integrations_Nav`).

| Item | Status | Slug / notes |
|------|--------|--------------|
| Overview | Done | `rwgc-integrations-hub` → category cards |
| Google Analytics | Done | `rwgcp-google-analytics` (Analytics) |
| Google Ads | Done | `rwgcp-google-ads` (Advertising) |
| Google overview | Done | `rwgcp-google` (links only; hidden from provider tabs) |
| Meta | Done | `rwgcp-meta` — placeholder only (Advertising) |
| Weather | Done | `rwgcp-weather` (APIs) |
| API keys | Done | `rwgcp-api-keys` (APIs; no setup wizard) |
| Elementor | Done | `geo-elementor` (Content builders) |
| Gutenberg | Done | `rwgc-integrations-gutenberg` (Content builders) |
| WooCommerce | Done | `rwgc-integrations-woocommerce` (Ecommerce) |
| GeoCore Pro platform | Done | `rwgcp-geocore-pro` (System services; `is_section_nav` false) |
| City/time settings | Done | `egp-city-settings`, `egp-time-settings` (hidden nav) |

## Settings

| Item | Status | Slug / notes |
|------|--------|--------------|
| Overview | Done | `rwgc-settings-hub` |
| General | Done | `rwgc-settings` |
| Geo database / tools | Done | `rwgc-tools` |
| Add-ons | Done | `rwgc-addons` |
| Satellite settings | Done | Via `RWGC_Admin_Settings_Nav` provider cards |

## Acceptance criteria mapping

- Targeting contains only eligibility concepts — **done** (experiments/Elementor moved out).
- Experiences contains variants, content, experiments, reports — **done**.
- Elementor under Integrations — **done**.
- Geo Content under Experiences — **done**.
- City settings not in Targeting primary nav — **done** (Integrations/Weather).
- Tests / Groups / Rule builder / Creatives not primary nav — **done**.
- Internal routes remain URL-accessible — **done** (`is_section_nav = false`).

## Future (optional depth, not blocking IA)

- Rich charts on Audience/Campaign insights (mode-aware `usage-page.php` has scoped empty states and CTAs as of 1.7.9).
- Dedicated Merchandising/Availability rule engines (currently entry screens linking to overlays/rules).
- Meta integration surface beyond Pro placeholder tab.

---

**Release:** Geo Core **1.7.9** — contextual integration categories + scoped provider screens. GeoCore Pro **0.1.39** — split Google routes. Satellite plugins with route-only changes should be released alongside for consistent shell tabs.
