# Capabilities

Dotted IDs (`geo.country`, `commerce.product.promote`). Types: `condition` | `action` | `context` | `goal`.

```json
{
  "id": "geo.country",
  "type": "condition",
  "label": "Country",
  "description": "ISO2 country code",
  "input_schema": {},
  "output_schema": {},
  "provider": "reactwoo-geocore",
  "version": "1",
  "entitlement": ""
}
```

Legacy portable slugs (`country`, `device`, …) normalize via `RWGC_Schema::LEGACY_CONDITION_ALIASES`. Unknown capability IDs are rejected by the normalizer; runtime handling of unregistered IDs is WP2–3.
