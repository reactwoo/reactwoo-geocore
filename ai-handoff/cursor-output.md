# Cursor output — UX opportunity review (first pass)

## Status

**done** (needs staging acceptance)

## Summary

Implemented capability-aware **UX opportunity review** (`ux_opportunity_review`) across Geo AI (primary) and Geo Core (suite capability discovery).

## Files changed

### reactwoo-geo-ai (v0.4.135)

- `includes/workflows/class-rwga-workflow-ux-opportunity-review.php` — workflow: remote AI with local deterministic fallback, structured cards, server-side action filtering, persistence to recommendations + intelligence actions.
- `includes/services/class-rwga-ux-opportunity-action-filter.php` — capability map wrapper, action gating, pending action row builder.
- `includes/class-rwga-workflow-registry.php` — register workflow.
- `includes/class-rwga-plugin.php` — require new classes.
- `includes/services/class-rwga-context-builder.php` — `build_ux_opportunity_review()` with commerce/product/pro slices.
- `includes/services/class-rwga-intelligence-action-applier.php` — `create_optimise_test_prefill` approval handler (redirect to Optimise Create Test).
- `includes/services/class-rwga-intelligence-optimise-handoff.php` — support `ux_opportunity_review`; read `source_id` from action_json.
- `includes/class-rwga-admin.php` — admin route, handler, inner nav.
- `admin/views/ux-opportunity-review-page.php` — review UI with capability badges and recommendation cards.
- `reactwoo-geo-ai.php`, `readme.txt` — version 0.4.135.

### reactwoo-geocore (v1.8.109)

- `includes/class-rwgc-suite-capability-map.php` — installed/licensed suite product map.
- `includes/functions-rwgc.php` — `rwgc_get_suite_capability_map()`, `rwgc_ux_opportunity_review_admin_url()`.
- `includes/class-rwgc-plugin.php` — require capability map class.
- `includes/class-rwgc-insights.php` — include `suite_capabilities` in compact payload.
- `admin/views/insights-ai-opportunities-page.php` — CTA to UX opportunity review when Geo AI active.
- `reactwoo-geocore.php`, `readme.txt` — version 1.8.109.

## What was not changed

- Geo Optimise / Geo Commerce / GeoCore Pro plugin code (handoff uses existing Optimise Create Test URL pattern only).
- Remote API workflow definitions on reactwoo-api (local fallback works without API changes).
- No auto-apply of live content; all actions remain approval-gated.
- No satellite duplication of Geo Core detection or evaluator.

## Commands run

```bash
php -l includes/workflows/class-rwga-workflow-ux-opportunity-review.php  # OK
php -l includes/services/class-rwga-ux-opportunity-action-filter.php     # OK
php -l includes/class-rwgc-suite-capability-map.php                      # OK
cd reactwoo-geo-ai && npm run package:zip   # reactwoo-geo-ai-0.4.135.zip
cd reactwoo-geocore && npm run package:zip    # reactwoo-geocore-1.8.109.zip
```

## Staging acceptance

1. Geo Core + Geo AI: run review from **Geo AI → UX opportunity review**; draft action pending; Pro/Optimise/Commerce cards show upgrade labels when products missing.
2. With Optimise: “Create Optimise test” link on intelligence actions; approve `create_optimise_test_prefill` redirects to prefilled Create Test.
3. With Commerce + WooCommerce: product context in review when product ID supplied.
4. With GeoCore Pro: advanced targeting card available (open rules) vs upgrade label without Pro.
5. Engine source shown: Remote Geo AI vs Local deterministic fallback.

## Release

Not committed/pushed unless user requests.
