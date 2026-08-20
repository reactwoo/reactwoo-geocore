# Entitlements

Commercial model: [PLAN.md](../architecture/PLAN.md). Cloud is a **replacing bundle** for covered plugin SKUs. Source-tracked grants exist so transitions cannot drop access; they are **not** a licence to double-bill.

```json
{
  "key": "cloud.commerce",
  "allowed": true,
  "limit": null,
  "source": "standalone",
  "valid_from": "2026-01-01T00:00:00Z",
  "valid_until": null
}
```

`source`: `standalone` | `cloud` (implementation may also record `individual_subscription` vs `cloud_bundle`). Each grant keeps source and validity dates.

Feature code must ask the provider — never WooCommerce product IDs, store customer IDs, or license JWT fields:

```php
RWGC_Entitlements::allows( 'cloud.commerce' );
reactwoo_entitlements_allows( 'cloud.commerce' );
RWGC_Entitlements::limit( 'sites.max' );
```

```text
effective_access(capability) =
    any currently valid grant for that capability
```

## Keys (WP15)

| Key | Meaning |
|-----|---------|
| `cloud.personalisation` | Cloud / Pro targeting (Geo Core Pro or Cloud bundle) |
| `cloud.commerce` | Commerce outcomes (Geo Commerce or Cloud growth/scale) |
| `cloud.optimise` | Experiments (Geo Optimise or Cloud growth/scale) |
| `cloud.components` | Component variants |
| `cloud.insights` | Analytics history |
| `sites.max` | Connected sites |
| `team_members.max` | Org seats |
| `history.days` | Retained insight days |

Covered SKU mapping per Cloud plan: [PLAN.md §3](../architecture/PLAN.md).

## Providers

- **StandaloneLicenseProvider** — existing local product licenses (`rwgc_is_pro_enabled`, suite map).
- **CloudEntitlementProvider** — heartbeat-cached snapshot of the Cloud **bundle** grant. No Cloud HTTP on the visitor render path.
- **Composite:** `allows(key)` if **any** currently valid grant for `key` is allowed. Connection to Cloud must not destroy a still-valid individual grant. After successful Cloud activation, covered individual subscriptions are commercially superseded; remaining overlap is transition/grace/rollback/legacy correction only. Implemented in `RWGC_Composite_Entitlement_Provider`.

Payment lapse: Cloud keeps configuration and applies a grace window; after grace, the Cloud grant ends without deleting WordPress content. A still-valid individual grant for a non-superseded or downgrade-selected product must continue.

Commerce is ReactWoo.com WooCommerce. Decision Cloud stores entitlement snapshots only. See [billing-providers.md](../architecture/billing-providers.md).
