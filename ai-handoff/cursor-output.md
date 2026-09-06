# Cursor output — critical bug hunt (bbe1e69 / 1.8.163)

## Status

**done** — critical Cloud re-pair stale-manifest bug fixed.

## Bug and impact

After pairing WordPress to Cloud site A and syncing, disconnecting (or pairing over) to Cloud site B left `rwgc_cloud_manifest_current` and `manifest_revision` from site A. `RWGC_Request_Decision` evaluated that cache with no site-id check. A leftover revision sent as `If-None-Match` could 304 against site B and keep serving site A’s Experience Slot variants.

Concrete trigger: pair + sync site A → disconnect → pair site B (or copy a paired DB and re-pair). Until a non-304 fetch of site B’s manifest succeeds, visitors see the previous workspace’s Cloud HTML.

## Root cause

`install()` already rejects `wrong_site` on ingest. `pair()` / `disconnect()` / `evaluate()` / `sync_manifest()` did not apply the same invariant. Gate D correctly keeps the last cache while **disconnected**; it must not keep a **foreign** cache after credentials change.

## Fix

- `RWGC_Cloud_Manifest_Store::discard_if_foreign_site()`
- `pair()` discards a foreign cache and resets `manifest_revision`
- `evaluate()` returns null when credentials exist and `manifest.site()` does not match
- `sync_manifest()` refuses to 304-hold a foreign cache

Disconnect without re-pair is unchanged (Gate D).

## Files changed

- `includes/cloud/class-rwgc-cloud-manifest-store.php`
- `includes/cloud/class-rwgc-cloud-pairing.php`
- `includes/cloud/class-rwgc-cloud-sync.php`
- `includes/decision/class-rwgc-request-decision.php`
- `tests/test-rwgc-cloud-connector.php`
- `tests/test-rwgc-request-decision.php`

## What was not changed

- Open PRs #1–#58 (not re-filed)
- Gate D disconnect-keeps-cache behavior
- Migration `is_imported()` site_id (admin workflow only)
- Experience Slot #56/#57/#58, LiteSpeed #55, settings #23/#24

## Commands run

```
php tests/test-rwgc-cloud-connector.php
php tests/test-rwgc-request-decision.php
php tests/test-rwgc-cloud-security.php
php tests/test-rwgc-cloud-migration.php
```

All passed.

## Remaining errors

None from this pass.
