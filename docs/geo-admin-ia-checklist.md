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

| Item | Status | Slug / notes |
|------|--------|--------------|
| Overview | Done | `rwgc-integrations-hub` |
| Elementor | Done | `geo-elementor` |
| Gutenberg | Done (entry) | `rwgc-integrations-gutenberg` |
| WooCommerce | Done (entry) | `rwgc-integrations-woocommerce` |
| Weather | Done | `rwgcp-weather`, `egp-city-settings` (hidden nav) |
| Google | Done | `rwgcp-google` |
| Meta | Done (entry) | `rwgcp-meta` |
| API providers | Done | `rwgcp-geocore-pro` |
| API keys | Done (entry) | `rwgcp-api-keys` |

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

- Split Audience/Campaign insights into dedicated report UIs (currently mode-aware `usage-page.php`).
- Dedicated Merchandising/Availability rule engines (currently entry screens linking to overlays/rules).
- Meta integration surface beyond Pro placeholder tab.

---

**Release:** Geo Core **1.7.7** documents this IA pass. Satellite plugins with route-only changes should be released alongside for consistent shell tabs.
