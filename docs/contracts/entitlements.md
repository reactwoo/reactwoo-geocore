# Entitlements

```json
{
  "key": "cloud.commerce",
  "allowed": true,
  "limit": null,
  "source": "standalone"
}
```

`source`: `standalone` | `cloud`.

Feature code must ask the provider — never Stripe price IDs, Paystack plan codes, or license JWT fields:

```php
RWGC_Entitlements::allows( 'cloud.commerce' );
reactwoo_entitlements_allows( 'cloud.commerce' );
RWGC_Entitlements::limit( 'sites.max' );
```

## Keys (WP15)

| Key | Meaning |
|-----|---------|
| `cloud.personalisation` | Cloud / Pro targeting |
| `cloud.commerce` | Commerce outcomes |
| `cloud.optimise` | Experiments |
| `cloud.components` | Component variants |
| `cloud.insights` | Analytics history |
| `sites.max` | Connected sites |
| `team_members.max` | Org seats |
| `history.days` | Retained insight days |

## Providers

- **StandaloneLicenseProvider** — existing local product licenses (`rwgc_is_pro_enabled`, suite map).
- **CloudEntitlementProvider** — heartbeat-cached snapshot when Cloud is connected. No Cloud HTTP on the visitor render path.
- Composite uses Cloud when connected **and** a snapshot exists; otherwise standalone.

Payment lapse: Cloud keeps configuration and applies a grace window; after grace, keys are `allowed: false` without deleting WordPress content.

Billing processors (Stripe, Paystack) are Decision Cloud adapters only. See [billing-providers.md](../architecture/billing-providers.md).
