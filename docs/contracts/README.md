# ReactWoo platform contracts (schema v1)

PHP value objects under `includes/contracts/`. Schema version: **`reactwoo_schema_version = 1`** (`RWGC_Schema::VERSION`, `RWGC_REACTWOO_SCHEMA_VERSION`).

| Doc | Contract |
|-----|----------|
| [capabilities.md](./capabilities.md) | Capability |
| [audiences.md](./audiences.md) | Audience + Condition groups |
| [experiences.md](./experiences.md) | Experience + ExperienceSlot |
| [variants.md](./variants.md) | Variant types |
| [manifest.md](./manifest.md) | Compiled Manifest |
| [events.md](./events.md) | Event + Goal |
| [entitlements.md](./entitlements.md) | Entitlement |

**WP1 scope:** serialisation/validation only. No Cloud sync, no Decision Runtime, no UI, no migration of existing rules.
