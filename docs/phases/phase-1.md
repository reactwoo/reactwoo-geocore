# Phase 1 — Core refactor (complete / in maintenance)

Aligned with `docs/geo-core-cursor-master-plan.md` §10 Phase 1.

## Architecture decisions

1. **Canonical model (page routing):** `RWGC_Page_Route_Bundle` = default page + ordered `RWGC_Variant` entries + legacy role (`master` | `variant`). Resolution goes through `RWGC_Page_Route_Resolver` + `RWGC_Fallback_Resolver`.
2. **Legacy storage unchanged:** Post meta (`RWGC_Routing::*`) remains the source of truth. `RWGC_Legacy_Route_Mapper` projects meta into the bundle. No DB migration required for Phase 1.
3. **Parity with pre-refactor behavior:** Master branch resolves variants via **published** variant child pages only (same query semantics as `find_variant_for_master_country`). Inline `country_page_id` on master is **not** used for redirects yet (documented in mapper).
4. **Filters preserved:** `rwgc_page_route_bundle` and `rwgc_route_variant_decision` are applied inside `RWGC_Routing::get_route_decision_for_page()` so all callers share one pipeline.
5. **`RWGC_Rule`:** Structural stub for future persistence; not wired to storage in Phase 1.

## Files touched (Geo Core)

| Area | Files |
|------|--------|
| Engine | `includes/engine/class-rwgc-context.php`, `class-rwgc-variant.php`, `class-rwgc-page-route-bundle.php`, `class-rwgc-fallback-resolver.php`, `class-rwgc-page-route-resolver.php` |
| Rules stub | `includes/rules/class-rwgc-rule.php` |
| Migration | `includes/migration/class-rwgc-legacy-route-mapper.php` |
| PHPUnit (engine) | `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/Engine/*` — run `composer test` from the plugin root |
| Routing API | `includes/class-rwgc-routing.php` — `get_page_route_bundle()`, `get_route_decision_for_page()`, `maybe_route_request()` uses resolver |
| Bootstrap | `includes/class-rwgc-plugin.php` — loads engine + mapper |
| Helpers | `includes/functions-rwgc.php` — `rwgc_get_page_route_bundle()`, `rwgc_get_page_route_decision()` |

## Public API

- `RWGC_Routing::get_page_route_bundle( $page_id, $config = null )`
- `RWGC_Routing::get_route_decision_for_page( $page_id, $context = null, $config = null )`
- `rwgc_get_page_route_bundle( $page_id, $config = null )`
- `rwgc_get_page_route_decision( $page_id, $context = null, $config = null )`

Call after **`rwgc_loaded`** (Geo Core boots on `plugins_loaded` priority 5).

## Migration notes

- No user-facing migration: existing meta keys and Elementor page settings continue to drive behavior.
- Add-ons filtering `rwgc_route_variant_decision` receive the same `$config` array as before. Optional third/fourth arguments (`$page_id`, `RWGC_Context`) were added in Phase 2; two-parameter callbacks are unchanged.

## Backward compatibility

- Redirect reasons unchanged: `variant_fallback_to_master`, `master_country_variant_match`, `none`.
- **Fix vs earlier bug:** `rwgc_route_variant_decision` must not run twice per request (single application inside `get_route_decision_for_page`).

## Risks

- **Order of variants:** Multiple variants for one master use priority + `Fallback_Resolver` sort; legacy free tier typically has one — verify if Pro adds multiple.
- **Inline master mapping:** Stored in meta but not used in resolver — intentional until Phase 2+ defines multi-rule behavior.

## QA

Manual regression checklist and rollback notes: `docs/qa/phase-1-manual-tests.md`.

## Next phase (recommended)

**Phase 2 — Multi-country** — active: see `docs/phases/phase-2.md`.

See `docs/AGENTS.md` for the default single-thread workflow.
