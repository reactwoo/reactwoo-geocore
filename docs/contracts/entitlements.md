# Entitlements

```json
{
  "key": "cloud.commerce",
  "allowed": true,
  "limit": null,
  "source": "standalone"
}
```

`source`: `standalone` | `cloud`. Feature code should eventually ask an EntitlementProvider (WP15 / WP2 facade), not inspect Stripe or license JWT fields directly. This contract is the shared shape only.
