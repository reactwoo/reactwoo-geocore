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

Legacy portable slugs (`country`, `device`, …) normalize via `RWGC_Schema::LEGACY_CONDITION_ALIASES`. Unknown capability IDs are rejected by the normalizer.

## Registry (WP2)

- Authoritative store: `RWGC_Platform_Capability_Registry` (not the UX product map `RWGC_Capability_Registry`).
- Helpers: `reactwoo_register_condition()`, `reactwoo_register_action()`, `reactwoo_register_context_provider()`, `reactwoo_register_goal()`, `reactwoo_register_capability()`.
- Extensions hook: `reactwoo_register_capabilities` (on `init` priority 5, after Core seeds).
- Collision: a different provider cannot silently replace an ID (`reactwoo_capability_collision`).
- Admin inspection: Geo Core → **Capabilities** (`rwgc-system-capabilities`).
- Optional WP Abilities bridge: `RWGC_WP_Abilities_Adapter` when `wp_register_ability` exists.
