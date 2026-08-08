# Cursor output

## Status

**done** — Critical bug hunt on tip `c50e0be`: fixed two high-severity issues on `cursor/critical-bug-investigation-84ea` (PR #52).

## Bugs fixed

### 1. Gutenberg post Geo Rule Builder never saved portable meta

**Impact:** Post/page sidebar visibility rules never persisted (`_rwgc_post_portable_targeting` stayed empty/stale). Toggle-off could leave `_rwgc_post_visibility_rules_enabled=yes`.

**Root cause:** `ReactWooRuleBuilder.mount` lacked `onChange`; builder only wrote via jQuery `textarea` events that React controlled meta ignored. Sequential single-key `updateMeta` clobbered sibling keys.

**Fix:** `onChange` → batched `metaValuesRef` patches; visibility toggle writes both flags in one call.

### 2. Admin `?rwgc_preview_country=` persisted simulated country into `rwgc_cc`

**Impact:** Documented dashboard preview could bake the simulated ISO2 into the 24h LiteSpeed/vary cookie. After exit (or logout), cache misses can store real-IP HTML under the simulated-country vary key — wrong geo content for real visitors until purge. Sibling of open #40 (Rule Tester path only).

**Root cause:** `RWGC_Cache_Compat::maybe_set_country_cookie()` always called `rwgc_get_visitor_country()`, which honors `RWGC_Preview::filter_geo_data`.

**Fix:** `RWGC_Preview::is_active()` + skip cookie persistence while admin preview (and Rule Tester preview) is active.

## Files changed

- `assets/js/rwgc-post-geo-editor.js`
- `tests/js/rwgc-post-geo-editor-sync.test.js`
- `includes/class-rwgc-preview.php`
- `includes/integrations/class-rwgc-cache-compat.php`
- `tests/Targeting/RWGCPreviewCacheCookieIsolationTest.php`
- `ai-handoff/cursor-output.md`

## What was not changed

- Open PRs #17–#51 (routing overlays, Suite Elementor copy, R2 latest, page-version spoof, portable fail-open, signed Rule Tester routing bypass beyond the cookie gate, etc.)
- Atomic Geo Visibility (already on main / previously hunted)

## Commands run and results

- `node --check assets/js/rwgc-post-geo-editor.js` — OK
- `node tests/js/rwgc-post-geo-editor-sync.test.js` — OK
- `php -l` on changed PHP — OK
- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCPreviewCacheCookieIsolationTest.php` — OK (2 tests, 3 assertions)

## Remaining

None for this hunt scope.
