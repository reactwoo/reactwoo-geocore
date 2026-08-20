# Current task

**Gate D — Cloud authoring loop (local)**

Wire cached Cloud manifests into visitor Experience Slots. Do not enable production Cloud commerce (`REACTWOO_CLOUD_BRIDGE_ENABLED`).

## Goal

Author in Decision Cloud → Publish → WordPress sync → qualifying visitor sees the variant. Cloud off → last cached manifest still applies. No Cloud HTTP on the visitor render path.

## Done in code (1.8.160)

- `RWGC_Request_Decision` provides `reactwoo_current_decision_result`
- Portal `type`/`op` aliases
- Request-only slot/variant overlay

## Still required

A live Local page load. Do not mark Gate D complete from unit tests alone.

## Do not

- Skip to PLAN.md §19 step 14
- Call Cloud HTTP after `template_redirect`
- Unhook Elementor add-ons
