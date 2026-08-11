# ReactWoo platform architecture

Canonical home for the **ReactWoo Cloud v1** product and technical plan, suite audits, and shared contracts.

| Document | Role |
|----------|------|
| [reactwoo-cloud-v1-architecture-and-delivery-plan.md](./reactwoo-cloud-v1-architecture-and-delivery-plan.md) | **Official product & technical plan** — vision, architecture, data model, MVP, gates |
| [work-packages.md](./work-packages.md) | Cursor-ready implementation work packages (WP0–WP20) |
| [current-state.md](./current-state.md) | WP0 audit of the existing suite (**complete**) |
| [cloud-migration-impact.md](./cloud-migration-impact.md) | WP0 migration risks and recommended order (**complete**) |
| [../contracts/](../contracts/) | WP1 shared platform contracts (`reactwoo_schema_version = 1`) |
| [../contracts/decision-runtime.md](../contracts/decision-runtime.md) | WP3 local Decision Runtime (additive; not on visitor render path yet) |

## Governing rule

Every ReactWoo product repo should load:

`.cursor/rules/reactwoo-platform.mdc` (`alwaysApply: true`)

## Core decision

> **There is one ReactWoo decision model, but two parts to its execution. Cloud authors and compiles decisions; ReactWoo Core executes them locally.**

Never introduce a ReactWoo Cloud request into the visitor page-rendering critical path.

## Build sequence (summary)

1. **Foundation:** WP0 → WP1 → WP2 → WP3  
2. **Convert suite:** WP4  
3. **Rendering:** WP5 → WP9 (local product without Cloud)  
4. **Cloud:** WP10 → WP13  
5. **SaaS value:** WP14 → WP16  
6. **Hardening:** WP17 → WP19  
7. **AI (deliberately last):** WP20  

Related legacy Geo Core plan: [`../geo-core-cursor-master-plan.md`](../geo-core-cursor-master-plan.md). That document remains the Geo Core phase history; **new cross-plugin platform work follows this architecture folder.**
