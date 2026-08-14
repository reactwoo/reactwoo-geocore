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
| [entitlements.md](./entitlements.md) | Entitlement (WP15); billing processors: [billing-providers.md](../architecture/billing-providers.md) |
| [capabilities.md](./capabilities.md) (WP2) | Platform Capability Registry |
| [decision-runtime.md](./decision-runtime.md) | Local Decision Runtime (not on render path) |
| [../performance/decision-runtime.md](../performance/decision-runtime.md) | Decision Runtime performance (WP19) |
| [experience-slots.md](./experience-slots.md) | Experience Slot API (WP5) |
| [elementor-experience-slots.md](./elementor-experience-slots.md) | Elementor adapter (WP6) |
| [gutenberg-experience-slots.md](./gutenberg-experience-slots.md) | Gutenberg block (WP7) |
| [components.md](./components.md) | Component System (WP8) |
| [variant-engine.md](./variant-engine.md) | Variant Engine (WP9) |
| [cloud-connector.md](./cloud-connector.md) | Cloud Connector WP (WP10) |
| [site-health.md](./site-health.md) | Site health statuses (WP17) |
| [recommendations.md](./recommendations.md) | Advisory AI recommendations (WP20) |
| [../security/threat-model.md](../security/threat-model.md) | Security pass (WP18) |
| [decision-cloud-api.md](./decision-cloud-api.md) | Decision Cloud backend (WP11) |
| [satellite-capabilities.md](./satellite-capabilities.md) | Pro / Commerce / Optimise adapters |

**WP1 scope:** serialisation/validation only. Runtime pieces (WP2–WP5) are additive and documented in the rows above.
