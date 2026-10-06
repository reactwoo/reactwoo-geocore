# Cursor output

## Status
done

## Task
Prepare Geo Core 1.8.168 on a review PR (no tag, no merge). Port candidate fixes #69, #62, #55, #64, and the #16 preview bypass, plus the missing-rule semantics from #37.

## Files changed
- Elementor country hydrate (`assets/js/rwgc-elementor-library-bridge.js`) so Update does not save an empty country list (#69)
- `RWGC_GeoIP::get_current_ip()` ignores client forwarding headers unless the TCP peer is Cloudflare or a private proxy (#62)
- LiteSpeed vary groups come from server-side country and page version, not client cookies (#55)
- Library rule lookup no longer recurses; inactive rules never match (#64 + decision 2026-10-06, #37)
- `filter_document_content()` no longer treats a bare `?elementor-preview` as an editor bypass (#16)
- Version 1.8.168 in the plugin header, `RWGC_VERSION`, `readme.txt` stable tag, and changelogs (including the missing 1.8.167 CHANGELOG entry)
- New/ported tests wired into `composer test:all` and the PHP ones into `.github/workflows/test.yml`

## What was not changed
- No trusted-proxy allowlist (open question for the owner; `rwgc_visitor_ip` remains the escape hatch)
- No editor warning when a block references a deleted rule
- No git tag and no merge
- Vendor autoload left as it was on main (Composer dev install is local only)

## Commands run
- `composer install --prefer-dist --no-progress`
- `vendor/bin/phpunit -c phpunit.xml.dist` — Tests: 97, Assertions: 366, Errors: 9, Failures: 7
- Every other `composer test:*` script in `test:all` — all exit 0

## Remaining errors
Pre-existing only:
- 9 errors: `RWGC_ContextAttributionTest` “headers already sent”
- 7 failures: `RWGCTargetingAssistantUiRegressionTest`

New and ported tests passed. PHPUnit count went from 91 to 97 (4 cache-compat tests, 2 unresolved-rule tests).
