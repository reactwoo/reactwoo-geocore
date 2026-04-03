# Phase 2 — Multi-country rules (scaffolding complete)

Aligned with `docs/geo-core-cursor-master-plan.md` §10 Phase 2.

## Goals

- Multiple conditions per source: **include** and **exclude** lists, optional **country groups** (stored registry + hooks; no second routing engine).
- Shared evaluation for **variants** (`RWGC_Variant`) and **rules** (`RWGC_Rule`) behind the same condition shape.
- Keep **legacy post meta** and `RWGC_Legacy_Route_Mapper` as the adapter until persisted rules ship.

## Architecture decisions

1. **`RWGC_Rule_Condition_Evaluator`** — Single place for `context_matches_conditions( array $conditions, RWGC_Context $context )`. Keys:
   - `countries_include` / legacy `country`
   - `countries_exclude` — visitor ISO2 in this list (after group expansion) → no match
   - `country_groups_include` / `country_groups_exclude` — slugs resolved via **`RWGC_Country_Groups`** (option `rwgc_country_groups`, plus filters `rwgc_country_groups` and `rwgc_country_group_countries`)
   - `country_groups` — legacy string ids; merged into the include set via `rwgc_expand_country_groups`
2. **`RWGC_Country_Groups`** — Registers named lists of ISO2 codes for conditions; Pro / extensions may filter the registry.
3. **`RWGC_Legacy_Route_Mapper`** — Masters with legacy inline `country_iso2` + `country_page_id` now emit an extra **`RWGC_Variant`** (`legacy_inline_{page_id}`, priority **55**) so redirects match DB-backed variants (priority **50**) when both exist.
4. **Empty visitor country** — no positive match (same as Phase 1).
5. **Include set empty** — no match (exclude-only rules need explicit includes or group expansion).
6. **`RWGC_Rule::matches_context()`** — optional publish gate + simple `start_at` / `end_at` scheduling when parsable.
7. **`rwgc_route_variant_decision`** — third and fourth parameters: `$page_id`, `$context` (backward compatible with two-argument listeners).

## Filters

| Hook | Args | Purpose |
|------|------|---------|
| `rwgc_expand_country_groups` | `array $iso2_codes`, `array $group_ids`, `RWGC_Context $context` | Return **additional** ISO2 codes to union into the include list (legacy `country_groups` key). Core registers a default handler via `RWGC_Country_Groups::init()` that reads the `rwgc_country_groups` registry. |
| `rwgc_country_groups` | `array $registry` | Filter the full slug → config map from the option store. |
| `rwgc_country_group_countries` | `string[] $iso2`, `string $group_id` | Adjust resolved countries for one group. |
| `rwgc_page_route_bundle` | unchanged | Still the extension point for altering the bundle before resolution. |

## Files touched (Geo Core)

| Area | Files |
|------|--------|
| Rules / evaluation | `includes/rules/class-rwgc-rule-condition-evaluator.php` |
| Country groups | `includes/engine/class-rwgc-country-groups.php` (`init()`, default `rwgc_expand_country_groups`) |
| Rules entity | `includes/rules/class-rwgc-rule.php` |
| Variant | `includes/engine/class-rwgc-variant.php` |
| Routing | `includes/class-rwgc-routing.php` |
| Bootstrap | `includes/class-rwgc-plugin.php` |
| Tests | `tests/Engine/RWGC_VariantTest.php`, `tests/test-rule-condition-evaluator.php`, `tests/bootstrap.php` |

## Persistence

- **Not required** for Phase 2 scaffolding: rules remain stubs until storage/API is defined.
- When persistence lands, load rules for a source and merge into the bundle or resolver in one pipeline (no duplicate GeoElementor-side “which page wins” logic).

## Consumer plugins (GeoElementor)

- **GeoElementor** resolves advanced Pro routing via `RW_Geo_Router` only inside the `rwgc_route_variant_decision` extension path; baseline routing remains `RWGC_Routing::get_route_decision_for_page()`. The extension hook registration in `geo-elementor` is currently disabled in code; when re-enabled, listeners may use the optional `$page_id` and `$context` arguments.

## Migration / compatibility

- Default legacy mapper variants still use `countries_include` only; **inline master mapping** now participates in resolution (behavior change: masters with valid inline meta redirect like `find_variant_for_master_country` + child variants).
- Variants built with excludes/groups apply when those keys are present (filters, Pro, or programmatic bundle edits).

## Risks

- **Group expansion** — if `country_groups` is set but the filter returns nothing, the include set may stay empty → no match. Document for integrators.
- **Exclude vs include** — exclude wins when the visitor’s country is listed.

## Regression

- **Automated:** `composer test` (PHPUnit engine tests) and `composer test:rules` (CLI script for `RWGC_Rule_Condition_Evaluator` + country groups). `composer test:all` runs both.
- **Manual smoke:** master + variant pages, `rwgc_route_variant_decision` add-on with 2 and 4 args, empty-country visitor.

## Next phase

**Phase 3 — Free UX** per master plan §10 (block, shortcode, preview polish).
