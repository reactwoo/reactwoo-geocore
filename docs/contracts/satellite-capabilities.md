# Satellite capability registration (WP4)

Satellites register capabilities on `reactwoo_register_capabilities` without replacing existing evaluators.

| Plugin | Adapter | Registers |
|--------|---------|-----------|
| GeoCore Pro | `RWGCP_Platform_Capabilities` | `traffic.campaign`, `traffic.audience`, weather.*, … |
| Geo Commerce | `RWGCM_Platform_Capabilities` | `commerce.*` conditions/actions + cart/product fields |
| Geo Optimise | `RWGO_Platform_Capabilities` | `experiment.assign`, `goal.*` |

## Entitlements (metadata only for now)

- `geocore_pro`
- `geo_commerce`
- `geo_optimise`

Feature gates should call `RWGC_Entitlements::allows()` (WP15). Satellite plugins may still use existing license classes until they are migrated onto this facade.

## Gate A

`RWGC_Decision_Parity::compare_country_in_rule()` + `composer test:decision-parity` proves a portable `country in […]` rule matches the Decision Runtime `geo.country` audience for the same visitor country.
