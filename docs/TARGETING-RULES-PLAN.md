# Unified Pro-aware targeting rules — plan & checklist

## Summary

Portable JSON rule sets (`enabled`, `mode`, `match`, `rules[]` with nested `conditions[]`) are **sanitized** in GeoCore, **evaluated** once via `RWGC_Targeting_Rule_Set_Evaluator` against `RWGC_Context_Snapshot`, and wired into **Elementor** and **Gutenberg** with **legacy country** fallbacks. GeoCore Pro registers **campaign**, **audience**, and **reactwoo** audience tokens via `rwgc_targeting_evaluate_condition`.

## Implementation checklist

### Core (reactwoo-geocore)

- [x] `docs/TARGETING-RULES-PLAN.md` (this file)
- [x] `includes/targeting/class-rwgc-targeting-rule-set-schema.php` — version, sanitize, strip Pro-only types when Pro inactive
- [x] `includes/targeting/class-rwgc-targeting-rule-set-evaluator.php` — top-level any/all, per-rule any/all, empty conditions = pass, empty `in` lists = pass
- [x] `includes/class-rwgc-plugin.php` — require new classes
- [x] `includes/class-rwgc-elementor.php` — controls + `filter_document_content` portable path
- [x] `includes/class-rwgc-gutenberg.php` — render + attribute passthrough
- [x] `blocks/geo-content/block.json` + `blocks/geo-content/index.js` — `portableTargeting` attribute + editor field

### Pro (reactwoo-geocore-pro)

- [x] `includes/class-rwgcp-portable-targeting.php` — `rwgc_targeting_evaluate_condition` for `campaign`, `audience`, `reactwoo:*`
- [x] `reactwoo-geocore-pro.php` — require + `init()`

### Follow-up (not in this PR)

- [x] Shared visual rule builder (`rwgc-rule-builder.js`) on Elementor, geo rules, block editor, and Targeting playground (JSON under Advanced)
- [x] Shared visual rule builder on Geo Commerce generic rule edit (portable JSON in rule meta; legacy rows when builder off)
- [x] Admin “Rules” screen persistence (post meta / CPT) using same schema — `rwgc_visibility_rule` CPT, Targeting → Visibility rules (`rwgc-visibility-rules`)
- [x] PHPUnit in CI (`composer.json` + `tests/TargetingRuleEvaluatorTest.php`, `.github/workflows/test.yml`)
- [x] `google_ads_campaign` snapshot enrichment — `campaign_id` from `campaignid` / `gad_campaignid`; Pro resolves synced entity id for portable `campaign` rules

## Test checklist (manual)

1. **Elementor — legacy:** enable geo, countries only, no portable switch → behaviour unchanged. Classic stacks keep **Advanced → Geo Visibility**.
1b. **Elementor Atomic (V4):** sibling **Geo Visibility** section via `RWGC_Elementor_Atomic_Geo` (`elementor/atomic-widgets/props-schema` + `controls`); same `egp_*` / `rwgc_*` keys; countries as comma-separated ISO text; Pro library Select; evaluator via `RWGC_Surface_Settings::normalize()` + nestable `should_render`.
2. **Elementor — portable country only:** paste JSON with one rule `country` `in` `["GB"]`, mode `show`, enabled `true` → UK sees content, others not.
3. **Empty country list:** condition `country` `in` `[]` omitted or empty array → rule still passes (all countries).
4. **Top-level `match`:** two rules, `any` vs `all` — verify OR vs AND across rules.
5. **Gutenberg:** `portableTargeting` set → overrides `showCountries`; empty → legacy block behaviour.
6. **Pro on:** campaign / audience JSON matches snapshot (`campaign` UTM vs synced name/id; `ga_audience` vs synced ids).
7. **Pro off:** JSON containing only Pro types after sanitize strips them → country-only behaviour or match-all empty rules.

## Schema example (reference)

See product brief in task thread; stored keys: `schema_version`, `enabled`, `mode`, `match`, `rules`.

## Weather facets (follow-up)

Shopping-weather merchandising (`weather_facet` condition, product affinity, Woo/Elementor/Gutenberg widgets) is specified in **`docs/WEATHER-FACETS-MERCHANDISING-PLAN.md`**.
