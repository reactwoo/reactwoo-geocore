# Cursor output

## Status
done

## Task
WP16 — Existing customer migration (explicit import, never flip on connect).

## Files changed

### Geo Core (`reactwoo-geocore` 1.8.152)
- `RWGC_Cloud_Migration_Translator` — portable `show_if` rules → Audiences (`country` → `geo.country`); `hide_if`, experiments, commerce reported unsupported
- `RWGC_Cloud_Migration` — detect / local preview / backup / import HTTP / explicit `management_mode` switch
- Cloud admin: counts, preview tables, Import, Switch to Cloud-managed; pairing stays local
- Helpers: `reactwoo_cloud_migration_preview()`, `reactwoo_cloud_import()`, `reactwoo_cloud_switch_management_mode()`
- Tests: `composer test:cloud-migration` (+ pairing assertion in connector tests)

### Decision Cloud (`reactwoo-decision-cloud` 0.7.0)
- `POST /api/v1/sites/:site/migration/import` (`dry_run` does not persist; does not set `management_mode`)
- `POST /api/v1/sites/:site/management-mode` `{ mode: cloud|local }`
- `JsonRepository.updateSite`
- Portal Sites table shows management mode
- Tests: `tests/migration.test.js` (42 total `npm test`)

## What was not changed
- Visitor render path still has no Cloud HTTP
- Pairing still sets `management_mode = local`
- Disconnect still keeps WP content, manifests, and now also `rwgc_cloud_migration_backup`
- Experiments and commerce outcomes are not auto-imported
- WP17 health reporting not started

## Commands run
- Geo Core `php tests/test-rwgc-cloud-migration.php` — pass
- Geo Core `php tests/test-rwgc-cloud-connector.php` — pass
- Decision Cloud `npm test` — 42 passed

## Remaining
- Gate D / Gate E still need a live site
- WP17 Failure handling + health
