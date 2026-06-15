# Weather facets & merchandising — plan & phases

Suite-wide plan for **shopping weather** (wet, dry, hot, cold, mild, windy, sunny, high_uv, poor_air, high_pollen) across targeting, WooCommerce, Elementor, and Gutenberg. Complements `docs/TARGETING-RULES-PLAN.md` (portable rule engine) and `docs/phases/phase-7.md` (Geo Commerce boundary).

## Summary

Merchants and product teams think in **basic weather that affects sales**, not WMO codes or numeric thresholds. GeoCore Pro **derives** a small facet list from BYOK weather APIs. Geo Core **evaluates** `weather_facet` in portable rules. Geo Commerce **tags products**, **boosts catalog order**, and ships **display widgets** (Elementor, block, shortcode) that surface relevant SKUs.

**User-facing:** checkbox facets only.  
**Platform-facing:** raw `condition`, `temperature_c`, `wind_kph` (and optional UV) stay on the snapshot for derivation and diagnostics.

## Product boundaries

| Plugin | Owns |
|--------|------|
| **reactwoo-geocore-pro** | BYOK fetch, cache, normalization, **`weather.facets[]` derivation**, `rwgc_weather_targets_configured` |
| **reactwoo-geocore** | Portable schema (`weather_facet`), evaluator wiring, shared rule builder facet picker, Geo Content block visibility, Elementor geo visibility controls |
| **reactwoo-geo-commerce** | Product facet meta, Commerce rule conditions, catalog boost, Weather Products widget/block/shortcode, Woo loop badges via existing rules |
| **geo-elementor** (optional) | City/geo Elementor rules — **does not** own weather fetch; may consume `weather_facet` via shared portable rules on elements |

Geo Commerce applies **outcomes** (display, sort, badges). Eligibility uses Geo Core `RWGC_Rule_Evaluator` via `RWGCM_Targeting_Adapter` — same as campaign/country rules today.

## Weather facets (merchant vocabulary)

Ten facets, **multi-select**, non-exclusive (visitor can be `wet` + `cold` + `windy`).

| Slug | Label | Example products |
|------|-------|------------------|
| `wet` | Wet / raining | Umbrellas, rain jackets, waterproof footwear |
| `dry` | Dry | Patio, BBQ, bikes, outdoor furniture |
| `hot` | Hot | Sunscreen, fans, swimwear, cold drinks |
| `cold` | Cold | Coats, gloves, heaters, hot beverages |
| `mild` | Mild / comfortable | Everyday apparel, mid-season layers |
| `windy` | Windy | Windbreakers, hair care, secure outdoor gear |
| `sunny` | Sunny / bright | Sunglasses, hats, UV skincare |
| `high_uv` | High UV | Sunscreen, SPF, UV-protective gear (UV index ≥ 6) |
| `poor_air` | Poor air quality | Air purifiers, pollution masks (US EPA index ≥ 3; WeatherAPI) |
| `high_pollen` | High pollen / allergies | Antihistamines, hay fever relief (pollen index ≥ 3; WeatherAPI Pro+) |

`poor_air` and `high_pollen` require **WeatherAPI.com** with `aqi=yes` / `pollen=yes`. Open-Meteo does not supply these signals today.

### Removed from merchant UI (keep internal only)

- Nine technical conditions (`drizzle`, `heavy_rain`, …)
- Numeric rules: `temperature`, `wind_speed`, `humidity`, `precipitation_probability`
- Free-typed measurements (no °C, km/h, or % inputs)

Legacy portable types may remain in the evaluator for backward compatibility but are **hidden** from builders and deprecated in docs.

## Derivation (GeoCore Pro)

Class: `RWGCP_Weather_Facets` (to implement) — called from `RWGCP_Weather_Service::finalize_payload()` or `merge_snapshot()`.

Default thresholds (override via `rwgcp_weather_facet_thresholds` filter, not merchant settings in v1):

| Facet | Rule |
|-------|------|
| `wet` | Normalized `condition` ∈ `drizzle`, `rain`, `heavy_rain`, `snow`, `thunderstorm` |
| `dry` | Not `wet` |
| `hot` | `temperature_c` ≥ 25 |
| `cold` | `temperature_c` ≤ 12 |
| `mild` | `temperature_c` > 12 and < 25 |
| `windy` | `wind_kph` ≥ 25 |
| `sunny` | `condition` ∈ `clear`, `partly_cloudy`, not `wet`, and `temperature_c` ≥ 15 (when temp missing, require clear/partly_cloudy only) |
| `high_uv` | `uv_index` ≥ 6 when present (v2) |

Snow → typically `wet` + `cold`. Thunderstorm → `wet` + often `windy`.

If `weather.available` is false (no coords, cache miss, API error), `facets` is `[]` — all surfaces **degrade gracefully** (no empty shop, hide widget or show fallback).

### Snapshot contract

```json
{
  "weather": {
    "available": true,
    "facets": ["wet", "cold", "windy"],
    "condition": "rain",
    "temperature_c": 8,
    "wind_kph": 32,
    "location_source": "visitor",
    "cache_hit": true
  }
}
```

PHP: `rwgc_get_context_snapshot()['weather']['facets']` after Pro weather is configured.

## Portable targeting

### Condition type

- **Type:** `weather_facet`
- **Operators:** `in`, `not_in` (UI: “includes any of”, “excludes”)
- **Value:** string array of facet slugs, e.g. `["wet", "cold"]`
- **Pro-gated:** yes (same as other advanced portable types)
- **Evaluation:** active when `weather.available` and intersection(non-empty) for `in`; Pro resolver in `RWGCP_Portable_Targeting`

### Product affinity (Geo Commerce)

- **Meta key:** `_rwgcm_weather_facets` — array of facet slugs (same vocabulary)
- **Match (default):** any overlap between product facets and visitor `weather.facets`
- **Filter:** `rwgcm_weather_product_match` for custom scoring

### Rule JSON example

```json
{
  "type": "weather_facet",
  "operator": "in",
  "value": ["wet", "windy"]
}
```

## Consumer surfaces

How each stack **consumes** weather facets.

### WooCommerce (native)

| Surface | Phase | Behaviour |
|---------|-------|-----------|
| **Product editor** | 2 | “Good for this weather” checkbox group (7 facets); bulk edit; optional category defaults |
| **Commerce rules** | 2 | Condition `visitor.weather_facet` → badges, notices, overlays, pricing (existing action applier) |
| **Shop / category archive** | 4 | Optional **boost** mode: reorder loop by facet overlap score (`woocommerce_product_query` / `posts_clauses`) |
| **Single product** | 2 | Existing `RWGCM_Product_Display_Apply` when rule conditions include `weather_facet` |
| **Shortcode** | 3 | `[rwgcm_weather_products limit="8" category="" columns="4" fallback="hide\|category\|message"]` |

Settings (Geo Commerce → Weather merchandising): enable boost on shop/category/search; coordinate mode inherits Pro weather location setting; fallback when weather unavailable.

### Elementor

| Surface | Phase | Owner | Behaviour |
|---------|-------|-------|-----------|
| **Geo visibility** (sections/widgets) | 1 | Geo Core | Shared `rwgc-rule-builder.js` — add `weather_facet` to FIELD_DEFS when Pro weather connected |
| **Geo Elementor / portable controls** | 1 | Geo Core + Elementor bridge | Same portable JSON + visual builder on `rwgc_portable_geo_targeting` / `egp_portable_geo_targeting` textareas |
| **Weather Products widget** | 3 | Geo Commerce | New Elementor widget: title, source (all/category/manual), limit, columns, empty state; renders WC product loop |
| **Weather strip** (optional v2) | 5 | Geo Commerce | Compact “Today: Wet & Cold” + linked products — uses snapshot facets for label text |

Elementor widget category: **WooCommerce** or **ReactWoo Geo** (match existing Geo Core Elementor registration in `includes/integrations/elementor/`).

### Gutenberg

| Surface | Phase | Owner | Behaviour |
|---------|-------|-------|-----------|
| **Geo Content block** | 1 | Geo Core | `portableTargeting` + rule builder — `weather_facet` in condition picker |
| **Weather Products block** | 3 | Geo Commerce | `rwgcm/weather-products` — inspector: title, query source, limit, layout; `render_callback` uses shared query class |
| **WooCommerce blocks** (future) | 5 | Geo Commerce | Filter hook on Product Collection / hand-picked block queries where WC exposes stable APIs |

Block registration: `blocks/weather-products/` in Geo Commerce; depends on `rwgc-rule-builder` only for visibility variant if block supports “hide when no weather”.

### Widgets & display summary

| Asset | Slug / handle | Plugin |
|-------|---------------|--------|
| Weather Products shortcode | `rwgcm_weather_products` | Geo Commerce |
| Weather Products block | `rwgcm/weather-products` | Geo Commerce |
| Elementor Weather Products | `rwgcm-weather-products` | Geo Commerce |
| Shared product query | `RWGCM_Weather_Product_Query` | Geo Commerce |
| Facet list API for JS | `rwgc_rule_condition_choices.weather_facets` | Geo Core + Pro filter |

## Shared editor context

Extend `RWGC_Targeting_Rule_Set_Schema::get_editor_context()` / `rwgc_portable_targeting_editor_context`:

```php
'weather_facets' => array(
  array( 'slug' => 'wet', 'label' => __( 'Wet / raining', 'reactwoo-geocore' ) ),
  // ...
),
'weather_connected' => (bool) apply_filters( 'rwgc_weather_targets_configured', false ),
```

Geo Commerce rule builder (`rwgcm-rule-builder.js`) and Geo Core `rwgc-rule-builder.js` both read `weather_facets` for checkbox UI.

## Phases & checklists

### Phase 1 — Facets foundation & visibility (engine + builders)

**Goal:** Visitor facets on snapshot; `weather_facet` in portable rules; visible in rule builders (no commerce yet).

| Repo | Tasks |
|------|--------|
| **geocore-pro** | [x] `RWGCP_Weather_Facets` derivation; attach `facets` to cached payload; [x] `eval_weather_facet` in `RWGCP_Portable_Targeting`; [x] `weather_facets` + `weather_connected` in editor context; [x] filter `rwgcp_weather_facet_thresholds`; [x] `tests/test-weather-facets.php` |
| **geocore** | [x] Add `weather_facet` to `PRO_CONDITION_TYPES` + schema defs; [x] `weather_connected` in `get_editor_context()`; [x] `rwgc-rule-builder.js` — facet multi-select when `weather_connected`; [x] Weather target provider updated to `weather_facet`; [ ] PHPUnit evaluator fixture for `weather_facet` (optional) |
| **geocore-pro** | [ ] Map legacy `weather_condition` → facets for read-only migration helper (optional) |

**Surfaces unlocked:** Elementor geo visibility, Geo Content block, Targeting playground, Commerce portable JSON (advanced).

**Manual tests:** Rule “show if weather includes wet” on Elementor section; block editor same; snapshot shows facets in diagnostics.

---

### Phase 2 — WooCommerce product tagging & rules

**Goal:** Merchants tag products; commerce rules react to visitor weather.

| Repo | Tasks |
|------|--------|
| **geo-commerce** | [ ] Product meta `_rwgcm_weather_facets` + product data tab UI; [ ] `RWGCM_Weather_Affinity::get_product_facets()` / `product_matches_visitor()`; [ ] Add `visitor.weather_facet` to `RWGCM_Condition_Library`; [ ] Wire through `RWGCM_Targeting_Adapter`; [ ] Bulk product editor; [ ] Category default facets (term meta) optional |
| **geo-commerce** | [ ] Loop/single badges via existing rules + `RWGCM_Product_Display_Apply` |

**Manual tests:** Tag umbrella `wet`; rule badge when wet; no badge when dry; Pro weather cache warm on staging.

---

### Phase 3 — Weather Products display (widget, block, shortcode)

**Goal:** Curated product row anywhere on the site.

| Repo | Tasks |
|------|--------|
| **geo-commerce** | [ ] `RWGCM_Weather_Product_Query` — query by facet overlap, order by score; [ ] Shortcode `rwgcm_weather_products`; [ ] Gutenberg block `blocks/weather-products/`; [ ] Elementor widget `includes/integrations/elementor/class-rwgcm-elementor-weather-products.php`; [ ] Shared template `templates/weather-products-loop.php`; [ ] Empty/fallback modes; [ ] Enqueue loop CSS compatible with WC / theme |

**Inspector controls (block + Elementor):** title, description, max products (4–12), columns, source (all / category / manual IDs), order (relevance / date / menu order), when no match (hide / fallback category / custom message), when weather unavailable (hide / show bestsellers / message).

**Manual tests:** Homepage block in rain → rain products; Elementor widget same; shortcode in classic post.

---

### Phase 4 — Catalog boost (shop & category archives)

**Goal:** Reorder native Woo loops without hiding catalog breadth.

| Repo | Tasks |
|------|--------|
| **geo-commerce** | [x] Settings screen section Weather merchandising; [x] `RWGCM_Catalog_Weather_Boost` on `woocommerce_product_query`; [x] Modes: off / boost (default) / filter-only; [x] Score = count of matching facets; [x] Performance: only run when `weather.available` and cache hit; [x] Filter `rwgcm_weather_catalog_boost_enabled` |

**Manual tests:** Shop page with mixed catalog; wet day → rain SKUs rise; dry day → different order; weather off → unchanged order.

---

### Phase 5 — Polish & extensions

| Item | Owner |
|------|--------|
| UV index fetch + `high_uv` facet | geocore-pro ✅ |
| AQI / pollen + `poor_air`, `high_pollen` facets | geocore-pro ✅ (WeatherAPI) |
| Store vs visitor weather coordinates | geocore-pro ✅ + commerce fallback |
| Weather strip Elementor widget | geo-commerce ✅ |
| Geo AI: suggest product facets from catalog | geo-ai ✅ |
| WooCommerce Product Collection block integration | geo-commerce ✅ |
| Import/export facet column for products | geo-commerce ✅ |
| Simulator overrides for facets in admin preview | geocore ✅ |

## Hooks reference (planned)

| Hook | Purpose |
|------|---------|
| `rwgcp_weather_facet_thresholds` | Adjust hot/cold/windy/sunny cutoffs |
| `rwgcp_weather_facet_labels` | Localize or extend facet labels |
| `rwgc_rule_condition_choices` | Add `weather_facets` choices |
| `rwgcm_weather_product_match` | Custom product/visitor match |
| `rwgcm_weather_catalog_boost_enabled` | Disable boost per request |
| `rwgcm_weather_products_query_args` | Alter widget/block query |

## Dependencies

- **Geo Core** active
- **GeoCore Pro** licensed + weather provider configured (Open-Meteo or WeatherAPI)
- **Geo Commerce** for product tagging, widgets, catalog boost
- **WooCommerce** for product surfaces
- **Elementor** (optional) for Elementor widget only

Weather cache warm (Pro WP-Cron or admin test) required for reliable front-end facets on public requests.

## Test checklist (release)

1. **Derivation:** Test connection in Pro → snapshot includes expected `facets` for known lat/lon.
2. **Visibility:** Elementor section `weather_facet` wet → visible only when wet.
3. **Gutenberg:** Geo Content block same rule → editor preview + front match.
4. **Product tag:** Product tagged `cold` appears in Weather Products block when visitor cold.
5. **Shortcode:** `[rwgcm_weather_products]` respects limit and category scope.
6. **Elementor widget:** Parity with block settings.
7. **Commerce rule:** Badge on loop when `weather_facet` matches.
8. **Boost:** Shop order changes with overlap; off setting restores default.
9. **Degrade:** No coords / cache miss → no PHP errors; widget hidden or fallback; shop order unchanged.
10. **Pro off:** `weather_facet` stripped from sanitized JSON; builders hide weather group.

## Migration notes

- Existing rules using `weather_condition`, `temperature`, etc. continue to evaluate but should migrate to `weather_facet`.
- `RWGC_Target_Provider_Weather` legacy targets (`temperature_band`) superseded by facets — update provider registration when Phase 1 ships.
- Product tagging is new meta; no automatic migration from tags/categories unless merchant uses category defaults tool.

## Related docs

- `docs/TARGETING-RULES-PLAN.md` — portable rule engine
- `docs/phases/phase-7.md` — Geo Commerce engine boundary
- `reactwoo-geo-commerce/docs/WEATHER-MERCHANDISING.md` — commerce implementation checklist
- GeoCore Pro `includes/weather/` — BYOK providers

## Status

**Phases 1–5 and v2 admin/integrations** — shipped. Operational roadmap: `reactwoo-geo-commerce/docs/WEATHER-MERCHANDISING-IMPROVEMENTS.md`.
