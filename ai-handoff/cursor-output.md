# Cursor output — Rule Tester impact inspector (v1.8.110)

## Status

**done** — implementation complete; local build/lint pending verification below.

## Suspected root cause (confirmed)

`fetchCompatibility()` sent only `buildContentPayload()` → `{ type, id, url }` with **no `context.page_type`**.

`check_compatibility()` → `compatibility_for_content()` → `document_context_from_content()` derived page type from the **post record** (Home variant → `other`), while Run Test used `context.page_type` from the form (`product`) via `merge_content_into_context()`.

Secondary: `snapshot_from_scenario()` omitted `gclid`, so Google Ads traffic branches could fail in the tester even when UTM fields matched.

## Proof — context shape

| Path | Before | After |
|------|--------|-------|
| Compatibility POST body | `{ rule_id, content: { type, id, url } }` | `{ rule_id, content: { type, id, url, page_type }, context: { country, device, page_type, request_uri, utm_*, gclid } }` |
| Run test POST body | `content` + `context` (context had page_type) | Same canonical `buildTesterContextPayload()` |
| Server compatibility doc context | `page_type: other` from post | `page_type: product` from simulated context; `natural_page_type: other` preserved |
| Evaluator scenario | Had page_type when form filled | Unchanged; gclid now included |

## Files changed

| File | Why |
|------|-----|
| `admin/js/rwgc-visibility-rule-tester.js` | `buildTesterContextPayload()`; compatibility/run use same payload; applied targets table; preview buttons; clearer detected-context chips |
| `admin/css/rwgc-rule-tester.css` | Applied targets table + result sections |
| `includes/class-rwgc-visibility-rule-tester.php` | `normalize_tester_request()`, `build_document_context()`, `compatibility_for_tester()`, `build_applied_targets()`, `build_preview_response()` |
| `includes/class-rwgc-visibility-rule-preview.php` | Pass `gclid` into scenario snapshot |
| `includes/class-rwgc-rule-tester-frontend-preview.php` | **New** — signed token, frontend context override, admin banner, no-cache, skip attribution cookies |
| `includes/class-rwgc-rest.php` | `POST /targeting/rule-tester/preview-url` |
| `includes/class-rwgc-visibility-rule-tester-assets.php` | Labels + `previewUrl` |
| `includes/class-rwgc-plugin.php` | Require + init frontend preview |
| `includes/context/class-rwgc-context-attribution.php` | `rwgc_context_attribution_should_persist` filter hook |
| `reactwoo-geocore.php`, `readme.txt` | v1.8.110 |

## Assignment lookup — before / after

- **Before:** Assignments fetched on content change; only shown in Applied Target mode dropdown; rule test did not list where rule is applied.
- **After:** `run()` scans assignments for selected content, filters by `rule_id`, returns `applied_targets[]` with mode, visibility outcome, and reason. Applied Target mode unchanged for single-element drill-down.

## Preview token / security

- HMAC-SHA256 signed JSON (`wp_salt('rwgc_rule_tester_preview')`), 15-minute TTL, bound to `uid` (logged-in admin).
- URL: `?rwgc_preview=1&rwgc_preview_token=SIGNED` — no unsigned country/device/page_type query overrides.
- Frontend: overrides `rwgc_geo_data` + `rwgc_context_snapshot_values`; `nocache_headers()`; skips attribution cookie writes; admin banner + exit link.
- Hook: `rwgc_rule_tester_preview_bootstrapped` for satellites (Optimise/analytics opt-out).

## What was not changed

- `RWGC_Rule_Evaluator` semantics
- Frontend visibility logic (except signed preview override path)
- Geo Commerce / Optimise / Geo AI / Pro

## Commands run

```bash
# Run after pull:
php -l includes/class-rwgc-visibility-rule-tester.php
php -l includes/class-rwgc-rule-tester-frontend-preview.php
php -l includes/class-rwgc-visibility-rule-preview.php
php -l includes/class-rwgc-rest.php
php -l includes/context/class-rwgc-context-attribution.php
npm run package:zip
```

## Remaining limitations

- Assignment discovery is Elementor-centric (`RWGC_Elementor_Assignment_Discovery` + `rwgc_rule_tester_assignments` filter); Gutenberg/Commerce assignments appear only if extensions hook the filter.
- Preview simulates visitor context on the **selected content URL**; it does not navigate to a separate product permalink when simulating product page type on a page variant (evaluator/tester still evaluate correctly; visual preview is page-based).
- PHPUnit not re-run locally in this pass (vendor/env dependent).

## Acceptance retest

1. Rule Tester → Free Delivery → Home (Variant) → Page type **Product** → compatibility must **not** say "current context is Other".
2. Run test with Google Ads preset → rule should match when country/device/page type match.
3. Applied targets table lists Elementor section/product assignments on Home Variant.
4. Open simulated preview → banner visible; visibility matches tester; no fake geo cookies after exit.
