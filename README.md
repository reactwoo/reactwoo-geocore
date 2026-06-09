# ReactWoo Geo Core

**Version:** 1.8.38  
**Plugin slug:** `reactwoo-geocore`

## Overview

ReactWoo Geo Core is the free foundation plugin for the ReactWoo Geo suite. It provides MaxMind-based visitor country detection, a shared rule-evaluation engine, page-variant routing, and admin surfaces for targeting content across Gutenberg, Elementor, shortcodes, and REST consumers. Core geo features work without a ReactWoo product license; optional suite integrations use **react-license** and **reactwoo-api** when enabled.

## Position in family

| Layer | Product | Role |
|-------|---------|------|
| Foundation | **Geo Core** (this plugin) | Detection, rules, routing, suite shell, REST discovery |
| Premium extension | GeoCore Pro | Licensing gate, Google/weather, portable targeting |
| Satellites | Geo Commerce, Geo Optimise, Geo AI | WooCommerce, experiments, AI workflows |
| Platform services | react-license, reactwoo-api, react-cloud | JWT, AI/updates API, Google OAuth |

Geo Core must be active before any satellite or GeoCore Pro feature runs. Satellites register with Core via `rwgc_loaded`, REST `/capabilities`, and the platform app-shell route registry.

## Key Features

### Available

- MaxMind GeoLite2 Country download, cache, and visitor detection (third-party MaxMind account required)
- Shortcodes: `[rwgc_country]`, `[rwgc_country_code]`, `[rwgc_currency]`, `[rwgc_city]`, `[rwgc_region]`, `[rwgc_if]`
- PHP helpers: `rwgc_get_visitor_country()`, `rwgc_get_visitor_currency()`, `rwgc_get_visitor_data()`
- REST: `/wp-json/reactwoo-geocore/v1/location`, `/capabilities` (discovery; no visitor PII on capabilities)
- Gutenberg **Geo Content** block and post-level geo visibility panel
- Elementor document/element geo controls and popup targeting (free baseline)
- Visibility rules library (`rwgc_visibility_rule` CPT) and shared rule builder
- Page variant routing (master + one secondary country mapping per page; branded `/_gc/{version}` URLs)
- `RWGC_Rule_Evaluator` portable targeting with fail-closed evaluation for archived/missing rules
- ReactWoo Geo platform admin shell (Overview, Targeting, Commerce, Insights, Integrations, Settings)
- Legacy Geo Elementor compatibility (deferred controls, popup guards, licence migration paths)
- Satellite updater framework (`RWGC_Satellite_Updater`) and suite hooks — see `docs/GEO_SUITE_HOOKS.md`

### In Progress

- Hardening Elementor popup delivery on variant URLs (ongoing 1.8.x series)
- Polish toward **v1.0** release candidate (admin IA, targeting parity, documentation)

### Planned

- WordPress.org stable **1.0.0** packaging and public distribution polish
- Deeper Meta / social integration surfaces (placeholder in Integrations hub today)

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.2+ (tested through 6.9) |
| PHP | 7.4+ |
| MaxMind account | GeoLite2 Country license (free tier from MaxMind) |
| Composer on server | **Not required** — `vendor/` is bundled in releases |

**Not required:** WooCommerce, Elementor Pro, ReactWoo product license (for core geo).

## Installation

1. Upload to `wp-content/plugins/reactwoo-geocore` or install the release zip via **Plugins → Add New**.
2. Activate **ReactWoo Geo Core**.
3. Open **Geo → Integrations → MaxMind (GeoLite2)** — enter MaxMind Account ID and license key, then download or upload the `.mmdb` database.
4. Test visitor detection on the same screen.

No `composer install`, SSH, or WP-CLI is required on customer sites.

## Configuration

| Area | Location | Notes |
|------|----------|-------|
| MaxMind | Integrations → System services → MaxMind | Third-party credentials; not a ReactWoo license |
| REST API | Settings | Enable `/location` and `/capabilities` when needed |
| Optional ReactWoo key | Settings → GeoCore Pro tab | For optional AI bridge to **reactwoo-api** only |
| Targeting debug | Settings / filters | `rwgc_targeting_debug` for popup trace logs |
| Suite modules | Filters | `rwgc_register_modules`, `rwgc_advanced_targeting_enabled` |

## Feature Entitlements

| Feature | Free (no ReactWoo license) | With GeoCore Pro license |
|---------|---------------------------|--------------------------|
| Country detection & shortcodes | Yes | Yes |
| Gutenberg / Elementor baseline targeting | Yes | Yes |
| Page variant routing (1 secondary) | Yes | Yes |
| Visibility rules library | Yes | Yes |
| Portable campaign/audience/time/weather conditions | No | Yes (via GeoCore Pro) |
| Google Ads / GA4 sync | No | Yes (via GeoCore Pro + **react-cloud**) |
| Satellite plugins (Commerce, Optimise, AI) | Separate product licenses via **react-license** | Per product |

## Integrations

| Integration | Dependency | Purpose |
|-------------|------------|---------|
| MaxMind GeoLite2 | MaxMind account | IP → country database |
| Elementor / Elementor Pro | Optional | Document, element, popup targeting |
| GeoCore Pro | This plugin + **react-license** | Premium targeting, profiles, weather |
| Geo Commerce / Optimise / AI | This plugin +各自的 license | Satellites consume Core hooks & REST |
| reactwoo-api | Optional JWT | AI orchestration, plugin updates for commercial slugs |
| react-cloud | GeoCore Pro + license Bearer | Google OAuth; tokens stay server-side |
| react-license | Commercial products | Domain-bound keys, entitlements, JWT minting |

## Developer Notes

- Boot constant: `RWGC_VERSION`; fires `rwgc_loaded`.
- Rule evaluation: `RWGC_Rule_Evaluator::matches()`; filter `rwgc_targeting_evaluate_condition`.
- REST discovery: `rwgc_get_rest_capabilities_url()`, `rwgc_get_rest_location_url()`.
- Route registry: `rw_geo_register_app_route()`, `rw_geo_app_url()`.
- Agent/release docs: `docs/AGENTS.md`, `docs/releases-and-git-tags.md`, `docs/GEO_SUITE_HOOKS.md`.
- PHPUnit and CI run on pull requests; tag `v*` triggers publish workflow.

## Known Limitations

- Country-level routing only in Core; city-level advanced routing remains in legacy Geo Elementor when used without full portable rules.
- Free page routing: one master + one secondary country mapping per page.
- MaxMind database must be refreshed manually or on schedule you configure; Core does not host geo data.
- Elementor popup edge cases on variant URLs may still require targeting debug and recent 1.8.x builds.
- Optional AI features require reachable **reactwoo-api** and valid JWT from **react-license**.

## Release Readiness

| Milestone | Status |
|-----------|--------|
| Core geo (detection, shortcodes, REST location) | **Shipped** |
| Platform admin shell & targeting library | **Shipped** |
| Elementor/Gutenberg targeting parity | **Shipped** (popup hardening ongoing) |
| Satellite discovery & updater framework | **Shipped** |
| **v1.0 RC** | **Near RC** — version series 1.8.x; targeting for public 1.0.0 |

## Compatibility

| Component | Supported versions |
|-----------|-------------------|
| WordPress | 6.2 – 6.9 (tested) |
| PHP | 7.4+ |
| WooCommerce | Optional; discovery via `rwgc_is_woocommerce_active()` |
| Elementor | Free baseline supported; Pro popups supported with ongoing fixes |
| GeoCore Pro | 0.1.x aligned with Core 1.8.x |
| Satellites | Geo Commerce 0.3.x, Geo Optimise 0.4.x, Geo AI 0.4.x |
| reactwoo-api | `/api/v5/*` auth, updates, geo-ai workflow proxy |
| react-license | JWT validation for commercial slugs |

## Roadmap

- **v1.0.0** — RC hardening, WordPress.org readiness, stable public API contracts
- Continued Elementor popup reliability on variant routes
- Integrations hub expansion (Meta placeholder → productized flow)

## Support

- Documentation: in-plugin **Geo Core → Usage** and `docs/` in the repository
- Commercial suite: [ReactWoo support](https://reactwoo.com/support)

## License

GPLv2 or later. See `license.txt`.
