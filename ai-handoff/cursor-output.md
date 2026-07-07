# Cursor output — Rule Tester result hierarchy + rendered impacts

## Status

**done** (Geo Core **v1.8.114** + Geo Commerce **v0.3.24**)

## Result hierarchy — before / after

| Before | After |
|--------|-------|
| Dominant headline: `Rule match: NO MATCH` | **Summary** block: Page match YES/NO, applied targets count, rendered impacts count, visible/hidden tallies |
| Single “Rule evaluation” section | Separate sections: Page/context evaluation · Applied target detection · Direct assignments · Rendered product impacts |
| Red “no match” styling even when targets exist | Neutral panel when page does not match but direct targets or rendered impacts were found; amber only when nothing was found |
| Generic assignment reasons | Spec copy for show_if / hide_if outcomes |
| Source column showed raw slugs (`elementor`) | Human labels (`Elementor`) via `source_label` + JS formatter |

## Direct assignment detection

- Scans Elementor `_elementor_data` on selected page/post/product for visibility-rule assignments matching the selected rule ID.
- Computes per-target visibility with `rwgc_visibility_mode_allows_render()` and parent-chain suppression (ancestor hidden → child hidden).
- Returns `applied_targets[]` with target label, type, source, mode, outcome (`visibility`), and reason.

## Rendered impact collector

- **Hook:** `rwgc_rule_tester_collect_rendered_impacts` (args: `$impacts`, `$tester_context`, `$rule_id`, `$content`).
- **Discovery hook:** `rwgc_rule_tester_discover_product_sources` for extensions.
- **Geo Core (`RWGC_Rule_Tester_Rendered_Impacts`):** Parses static product IDs from post content shortcodes, Woo blocks, Elementor product widgets; evaluates products with `_geocore_product_rule_ids` for the selected rule using simulated tester context (uses existing visibility mode helpers — no duplicate evaluator).
- **Dynamic grids:** When `[products category="…"]` / featured / best-selling tags are detected, sets `dynamic_query_detected` and falls back to products linked to the rule meta; shows preview note.
- **Geo Commerce (`RWGCM_Rule_Tester_Rendered_Impacts`):** Reports commerce rule actions (`product_visibility`, `price_adjustment`, badges/overlays) for discovered products when portable targeting JSON matches the selected visibility rule.

## Geo Commerce integration

**Required** — thin hook in `reactwoo-geo-commerce` (v0.3.24). No WooCommerce filtering logic duplicated in Geo Core.

## Response shape — before / after

**Before (`run()` extras):**
```json
{
  "applied_targets": [],
  "preview": {},
  "document_context": {}
}
```

**After:**
```json
{
  "result_summary": {
    "page_match": false,
    "page_match_label": "NO",
    "applied_targets_count": 2,
    "rendered_impacts_count": 1,
    "visible_outcomes": 1,
    "hidden_outcomes": 2,
    "why_page_no_match": ["Home (Variant) is a page/variant, not a product page.", "Traffic did not match any branch."]
  },
  "applied_targets": [
    {
      "target_label": "Free Delivery Banner",
      "target_type": "section",
      "source": "elementor",
      "source_label": "Elementor",
      "mode_label": "Hide when rule matches",
      "visibility": "visible",
      "reason": "The rule did not match, so this hide-on-match target remains visible."
    }
  ],
  "rendered_impacts": [
    {
      "target_type": "product",
      "source": "woocommerce_shortcode",
      "source_label": "WooCommerce shortcode",
      "product_id": 123,
      "product_name": "Winter Jacket",
      "rule_id": 456,
      "rule_label": "Free Delivery",
      "outcome": "hidden",
      "reason": "This product was removed from the rendered shortcode output by a geo product visibility rule."
    }
  ],
  "rendered_impacts_meta": {
    "dynamic_query_detected": false,
    "note": ""
  },
  "preview": { "url": "…", "expires": 0 }
}
```

## Files changed

**Geo Core**
- `includes/class-rwgc-rule-tester-rendered-impacts.php`
- `includes/class-rwgc-visibility-rule-tester.php` — `result_summary`, `rendered_impacts`, `source_label`, reason copy
- `includes/class-rwgc-plugin.php`
- `admin/js/rwgc-visibility-rule-tester.js`
- `admin/css/rwgc-rule-tester.css`
- `includes/class-rwgc-visibility-rule-tester-assets.php`

**Geo Commerce**
- `includes/class-rwgcm-rule-tester-rendered-impacts.php`
- `includes/class-rwgcm-plugin.php`

## Commands run

```bash
php -l includes/class-rwgc-visibility-rule-tester.php
php -l includes/class-rwgc-rule-tester-rendered-impacts.php
php -l includes/class-rwgcm-rule-tester-rendered-impacts.php
```

## Remaining limitations

- Dynamic Woo shortcodes do not execute a live product query in admin; `dynamic_query_detected` triggers meta-linked fallback + preview note.
- Geo Commerce collector links to the selected visibility rule only when portable JSON matches exactly.
- Commerce `product_visibility` storefront filter may differ from rule-store evaluation in edge cases; preview remains visual source of truth.
- **Next hook (optional):** Geo Commerce could register a runtime collector during simulated shortcode render (`rwgc_rule_tester_simulate_product_query`) to report exact query results without static parsing.

## Acceptance retest

1. Free Delivery + Home Variant + non-matching traffic → **Page match: NO**, **Applied targets found: 2**, section visible / container hidden with spec reasons; no misleading “rule missing” headline.
2. Product in shortcode with geo rule meta → **Rendered product impacts** lists product name, source, outcome, reason.
3. Preview link unchanged; dynamic grids show preview confirmation note.
