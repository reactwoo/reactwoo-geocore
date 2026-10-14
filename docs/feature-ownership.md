# Geo Core and Geo Core Pro feature ownership

Geo Core evaluates country detection and country visibility. Geo Core Pro evaluates advanced personalisation when a standalone licence or a Decision Cloud `cloud.personalisation` grant is active. An unlicensed Pro install does not register those resolvers.

Stored rules, page meta, and legacy identifiers are kept. A condition with no registered resolver does not match.

## Ownership

| Capability | Owner | Core files | Pro files | Migration |
| --- | --- | --- | --- | --- |
| Country detection, city, region, and currency display | Core | `includes/targeting/providers/class-rwgc-target-provider-geo.php` still resolves those snapshot fields. The registry advertises `country` only. | None | Shortcodes and REST stay. Region, city, and currency are not rule conditions. |
| Country and country-group visibility | Core | `includes/targeting/class-rwgc-rule-evaluator.php`, `includes/targeting/class-rwgc-targeting-rule-set-schema.php` (`FREE_CONDITION_TYPES`), `assets/js/rwgc-rule-builder.js` | None | Unrestricted. No country, rule, or section cap. |
| Country targeting in Elementor Classic, Atomic V4, popups, and Gutenberg | Core | Existing Elementor and block controls | None | Stored `egp_*` keys stay. |
| Shared visitor snapshot for satellites and Decision Cloud | Core | `includes/context/` and `RWGC_Context_Resolver::collect_provider_values()` | Pro may enrich Google entities when entitled | Context collection stays in Core so Optimise and Commerce keep their inputs. |
| Device, OS, and browser | Pro | Provider class remains for snapshot collection | `RWGCP_Advanced_Conditions::eval_device_type`, provider added by `RWGCP_Bootstrap::filter_target_provider_classes` | Stored rules kept. They match only while Pro is entitled. |
| Language and locale | Pro | Snapshot collection stays | `eval_language`, `eval_locale` | Same preservation rule. |
| Time of day, day of week, clock windows, and dates | Pro | Snapshot buckets stay | `eval_time_of_day`, `eval_day_of_week`, plus existing `eval_time`, `eval_day`, `eval_date` | Same preservation rule. |
| UTM, click IDs, and traffic attribution | Pro | Attribution snapshot stays | `RWGCP_Portable_Targeting` campaign and UTM resolvers, plus `eval_utm_content` and `eval_utm_term` | Same preservation rule. |
| New and returning visitors, logged-in state | Pro | Cookie and user snapshot stay | `eval_returning_visitor`, `eval_new_visitor`, `eval_logged_in` | Same preservation rule. |
| Page type, request URL, and compound groups | Pro | None executed | `eval_page_type`, `eval_request_uri`, `eval_condition_group` | Same preservation rule. |
| GA4 audiences, Google Ads, weather, profiles | Pro | No resolvers | Existing Pro evaluators, gated on `is_licensed()` | Same preservation rule. |
| Full-page variants | Pro | Core does not boot routing. `rwgc_page_variants_available` defaults false | `includes/page-versions/`, `eval_page_version_url` | Unlimited. Stored pages and `_rwgc_route_*` meta are not deleted. |
| Geo Optimise experiments | Optimise | Experience-slot engine stays in Core `includes/variants/` | None | Independent of page-version routing. |
| Geo Commerce outcomes | Commerce | Eligibility still uses `RWGC_Rule_Evaluator` | Commerce provider is advertised when Pro is entitled | Commerce continues to apply pricing and overlays. |

## Contract

- Core `default_resolvers()` returns `country` and `country_group` only.
- Every other condition type is extension-owned. `preserve_inert_condition()` keeps type, operator, and value when Pro is off.
- Pro `register_pro_resolvers()` returns the Core map unchanged unless `RWGCP_Bootstrap::is_licensed()`.
- The shared rule builder reads `extra_fields` from `rwgc_portable_targeting_editor_context`. Pro fills that list when entitled. There is one builder and no second settings screen.
- Platform capabilities from Core are `geo.country`, `geo.country_group`, and the country visibility action. Pro registers the advanced IDs only when entitled.

## Branches

- Core: `country-only-free` (version 1.9.7, not tagged).
- Pro: `advanced-targeting-ownership` (version 0.1.56, not tagged).
- Do not merge, tag, or submit `wporg-free-features-1.9.7`.
