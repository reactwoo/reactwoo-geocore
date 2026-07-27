# Cursor output — Elementor widgets-config fix + release

## Status

**done** — Geo Core **v1.8.119** + WHMCS Bridge **v1.1.2.2** (request-level caches + Loop Grid idempotence).

## Root cause addressed

Elementor `get_widgets_config` rebuilds control stacks; Geo Core re-queried/serialised visibility rules and WHMCS rebuilt option lists without request caches. Loop Grid inject lacked a per-stack guard across multiple query section IDs.

## Fix

### Geo Core
- `RWGC_Rule_Registry`: request-level static cache for library + legacy rows
- `RWGC_Elementor_Elements`: cache enriched library rows by document post id; SELECT options cache titles-only (full JSON stays in `rwgcElementorLibrary` localize)

### WHMCS Bridge
- `RW_WHMCS_Elementor_Option_Cache`: shared caches for templates, product groups, TLDs, currencies (ids-first queries)
- Loop Grid inject: per-stack idempotence + existing-control check

## Files changed

Geo Core: rule-registry, elementor-elements, debug helper (opt-in), version/readme
WHMCS: option-cache, loop-grid inject, three widgets, bridge bootstrap, debug helper, version/readme

## Acceptance

- Rule library / option lists loaded once per request type
- Loop Grid section registered once per stack
- No evaluator/pricing/routing changes
