# Current task

Investigate Elementor’s Elements panel failing to load when the ReactWoo plugin suite is active.

## Problem

Opening an Elementor document causes the Elements panel to spin indefinitely.

The widget-configuration AJAX request fails:

```text
POST /wp-admin/admin-ajax.php
HTTP 503 Service Unavailable

common.min.js → sendBatch()
editor.min.js → requestWidgetsConfig()
```

## Diagnostic constraints

- Do not patch production until evidence identifies the owner.
- Add temporary opt-in instrumentation gated by `RW_ELEMENTOR_CONFIG_DEBUG`.
- Isolation test: all → disable WHMCS → disable WHMCS+Geo Core → API Manager / Support Portal → Flow last.

## Suspected owners

- Geo Core repeated rule/control construction
- WHMCS Bridge widget option construction / Loop Grid duplicate injection
- API Manager or Support Portal global request work
- Server/WAF limit unrelated to the suite
