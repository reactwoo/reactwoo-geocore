# Cursor output

## Status
done

## Task
Ship Decision Cloud pairing in Geo Core (no production mu-plugin).

## Files changed
- `includes/cloud/class-rwgc-cloud-config.php` — default API base `https://decision.reactwoo.com/api/v1`; migrate stored `cloud.reactwoo.com/api/v1`
- `includes/cloud/class-rwgc-cloud-health.php` — health copy uses Decision Cloud URL
- `includes/cloud/class-rwgc-cloud-admin.php` — skip duplicate submenu when shell route exists
- `includes/class-rwgc-admin-route-registry.php` / `class-rwgc-admin.php` / `class-rwgc-admin-integrations-nav.php` — Cloud under Integrations → System services
- Version 1.8.157 headers, changelog, readme, cloud-connector / Gate D docs, tests

## What was not changed
- Local mu-plugin for `127.0.0.1:3040` (still valid on Local only)
- Decision Cloud service
- `REACTWOO_CLOUD_BRIDGE_ENABLED` on reactwoo.com
- vendor / packager scripts

## Commands run
- `composer test:cloud-security`
- `composer test:cloud-health`

## Remaining
- Update staging.aplenty.co.uk to Geo Core 1.8.157, then pair from Integrations → ReactWoo Cloud.
