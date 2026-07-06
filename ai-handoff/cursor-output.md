# Cursor output — Rule Tester result hierarchy + rendered impacts (v1.8.111)

## Status

**done**

## Result hierarchy — before / after

| Before | After |
|--------|-------|
| Dominant headline: `Rule match: NO MATCH` | **Summary** block: Page match YES/NO, applied targets count, rendered impacts count, visible/hidden tallies |
| Single “Rule evaluation” section | Separate sections: Page/context evaluation · Applied target detection · Direct assignments · Rendered product impacts |
| Red “no match” styling when targets exist | Neutral panel styling when page does not match but targets/impacts were found |
| Generic assignment reasons | Spec copy for show_if / hide_if outcomes |

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
    "why_page_no_match": ["…"]
  },
  "applied_targets": [],
  "rendered_impacts": [],
  "rendered_impacts_meta": {
    "dynamic_query_detected": false,
    "note": ""
  }
}
```

## Rendered impact collector

- **Geo Core:** `RWGC_Rule_Tester_Rendered_Impacts` — discovers product IDs in post content shortcodes, Woo blocks, and Elementor product widgets; evaluates products with `_geocore_product_rule_ids` matching the selected visibility rule using simulated tester context (no duplicate evaluator logic).
- **Hook:** `rwgc_rule_tester_collect_rendered_impacts` (+ `rwgc_rule_tester_discover_product_sources` for extensions).
- **Geo Commerce:** `RWGCM_Rule_Tester_Rendered_Impacts` — reports matching commerce rule actions (`product_visibility`, `price_adjustment`, display overlays) for discovered products when portable targeting matches the selected visibility rule JSON.

## Geo Commerce integration

**Required** — thin hook implementation in `reactwoo-geo-commerce` (no filtering logic duplicated in Geo Core).

## Files changed

**Geo Core**
- `includes/class-rwgc-rule-tester-rendered-impacts.php` (new)
- `includes/class-rwgc-visibility-rule-tester.php` — `result_summary`, `rendered_impacts`, reason copy
- `includes/class-rwgc-plugin.php`
- `admin/js/rwgc-visibility-rule-tester.js`
- `admin/css/rwgc-rule-tester.css`
- `includes/class-rwgc-visibility-rule-tester-assets.php`
- `reactwoo-geocore.php`, `readme.txt` → v1.8.111

**Geo Commerce**
- `includes/class-rwgcm-rule-tester-rendered-impacts.php` (new)
- `includes/class-rwgcm-plugin.php`

## Commands run

```bash
php -l includes/class-rwgc-rule-tester-rendered-impacts.php
php -l includes/class-rwgc-visibility-rule-tester.php
php -l includes/class-rwgcm-rule-tester-rendered-impacts.php
npm run package:zip   # geocore
```

## Remaining limitations

- Dynamic Woo shortcodes (`[products category="…"]`, featured/best-selling, etc.) set `dynamic_query_detected` and show preview note — static ID parsing only.
- Geo Commerce collector only links to the selected visibility rule when portable JSON matches exactly.
- Commerce `product_visibility` storefront hook may still be partial; tester reports rule outcomes from rule store, not live `woocommerce_product_is_visible` filter.

## Acceptance retest

1. Free Delivery + Home Variant + non-matching traffic → **Page match: NO**, **Applied targets found: 2**, targets table with correct show/hide reasons, no misleading “rule missing” headline.
2. Product in shortcode with geo rule meta → listed under **Rendered product impacts** with product name, source, outcome, reason.
3. Preview link unchanged.
