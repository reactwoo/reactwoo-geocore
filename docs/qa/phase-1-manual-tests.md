# Phase 1 — Manual QA checklist

Companion to `docs/phases/phase-1.md`. Covers legacy page routing, `rwgc_page_route_bundle` / `rwgc_route_variant_decision`, and empty-country behavior.

## Automated coverage (engine)

PHPUnit exercises the resolver and related engine units (no full WordPress load). From the plugin root:

```bash
composer test
```

For Phase 2 rule-condition coverage (include/exclude, `RWGC_Country_Groups`), also run `composer test:rules`, or `composer test:all` to run PHPUnit and that script together.

Notable file: `tests/Engine/RWGC_PageRouteResolverTest.php` — empty country, master/variant match, variant fallback. See also `RWGC_FallbackResolverTest.php`, `RWGC_ContextTest.php`, `RWGC_VariantTest.php`.

Full-plugin routing filters (`rwgc_page_route_bundle`, `rwgc_route_variant_decision`) and `maybe_route_request()` require manual or integration tests in WordPress.

## Code verification (what to expect)

| Area | Behavior |
|------|----------|
| Legacy mapping | `RWGC_Legacy_Route_Mapper::bundle_from_legacy_config()` projects post meta into `RWGC_Page_Route_Bundle`. Masters load variants from published variant child pages only (same idea as `find_variant_for_master_country`). Inline `country_page_id` on master is not used for redirects in Phase 1. |
| Resolution | `RWGC_Page_Route_Resolver::resolve()` — reasons: `master_country_variant_match`, `variant_fallback_to_master`, `none`. |
| `rwgc_page_route_bundle` | Applied in `RWGC_Routing::get_page_route_bundle()` and again inside `get_route_decision_for_page()` (signature: bundle, config, page id). |
| `rwgc_route_variant_decision` | Applied once at the end of `get_route_decision_for_page()` (signature: decision array, config, optional page id, optional context — two-arg listeners remain valid). |
| Empty visitor country | `maybe_route_request()` returns early when `rwgc_get_visitor_country()` is empty — no redirect. Resolver returns `none` / target `0` when context country is empty. |
| Invalid meta country | `sanitize_config()` clears invalid ISO2 and clears `country_page_id` when ISO2 is empty. |

## Manual test steps

1. **Prereqs** — Geo Core active; geo API returns a known country in normal conditions. Optional: Geo Core debug mode for `[RWGC Routing]` logs.

2. **Master → variant** — Master has routing enabled; one published variant child for country X. Visit master as visitor from X. **Expect:** 302 to variant; reason `master_country_variant_match` if you inspect `rwgc_get_page_route_decision()`.

3. **Variant → master** — Visit variant URL as visitor from a country **other** than the variant’s. **Expect:** 302 to master; reason `variant_fallback_to_master`.

4. **No match** — Visitor country matches master region but no variant exists for that country. **Expect:** no redirect; reason `none`.

5. **Empty visitor country (front)** — Simulate empty country (e.g. filter `rwgc_get_visitor_country` to `''` in a temporary mu-plugin, or conditions where API returns empty). Load a page that would otherwise route. **Expect:** no redirect.

6. **Empty country (API)** — After `rwgc_loaded`, call `rwgc_get_page_route_decision( $page_id, new RWGC_Context( '' ) )`. **Expect:** `target_page_id` = 0, `reason` = `none`.

7. **Filters** — Temporary callbacks on `rwgc_page_route_bundle` and `rwgc_route_variant_decision`; load one routed page once. **Expect:** variant decision filter runs **once** per `get_route_decision_for_page()` call.

8. **Loop guard** — If misconfiguration can ping-pong two URLs, **expect:** redirect blocked when the transient loop guard applies.

9. **Bypass** — Admin, AJAX, REST, cron, feeds, Elementor preview query args. **Expect:** no front-end redirect from `maybe_route_request()`.

## Rollback

- Phase 1 does **not** add a DB migration. Roll back by restoring the previous plugin zip or git revision. Post meta is unchanged.
- To stop routing without removing the plugin: disable routing on affected pages or deactivate Geo Core.
- Remove temporary mu-plugins/snippets used for testing.

## Security spot-check

- Redirects use `wp_safe_redirect`.
- Route config is read from post meta and passed through `sanitize_config()`.
