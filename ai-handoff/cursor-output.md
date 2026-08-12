# Cursor output

## Status

done

## Summary

Critical hunt on tip `c50e0be` / branch `cursor/critical-bug-investigation-1722` (post-#55 LiteSpeed fix). **NO_NEW_CRITICAL** — no different concrete critical with trigger/impact outside open PRs #1–#55 and prior near-misses.

## Verdict

`NO_NEW_CRITICAL`

### Near-misses (fail the bar)

1. **WooCommerce product geo is catalog-only** (`includes/integrations/woocommerce/class-rwgc-product-visibility.php` → `woocommerce_product_is_visible`). Direct URL / purchase still allowed. Owned by Geo Commerce by design; not a Core-only critical.
2. **Rule registry request cache never invalidated** (`RWGC_Rule_Registry::$rwgc_library_rows_cache`). Per-request only; no concrete mid-request frontend wrong-visibility without exotic same-request meta mutation. Stale-ID **recursion crash** remains tip-resident but is already **#25**.
3. **`enabled: false` / missing `enabled` → `matches() === true`** (`RWGC_Rule_Evaluator` ~92–94 + schema `! empty($data['enabled'])`). Correct passthrough for default `show_if`; broken for `hide_if` (hides everyone). Rule Builder always writes `enabled: true`, so realistic trigger is hand-edited/imported JSON or intentional disable of a hide rule — awkward vs empty-list match-all product decisions.

### Also re-checked (skipped / already PR'd)

- LiteSpeed / `RWGC_Cache_Compat` — **#55** (this branch)
- Popup PHP `ensure_allowed_popups*` site-wide inject — **#26** (gates PHP + JS; no worse XSS/auth issue beyond that on tip)
- Cloudflare/CDN vary — no CF integration; cookie-only is a gap, not an actively wrong vary like LiteSpeed
- Routing Elementor overlays — **#45/#47/#48/#50**
- Provenance `infer_provenance_from_rule_set` wrong shape — admin-only; `mark_variant_archived` has no callers
- GeoIP XFF trust — longstanding; skipped per prior decision
- Satellite updater / uninstall — no product data-loss critical (uninstall clears settings only; force-check needs `update_plugins` + nonce)
- Shortcodes / Gutenberg geo-content — surface evaluator paths; unresolved fail-open covered by **#31/#37**
- strcmp/type-juggling auth — no secret `strcmp`/`==` auth gates found

## Files changed

None (investigation only).

## Commands run

- `gh pr list` / `gh pr view` for #21, #25, #26, #55
- Static reads of WooCommerce product meta/visibility, shortcodes, routing, popups PHP, rule registry/evaluator, cache compat, satellite updater, uninstall, provenance, page-version routing, surface settings, Gutenberg post geo

## Remaining errors

None for this pass.
