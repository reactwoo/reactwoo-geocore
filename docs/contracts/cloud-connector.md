# Cloud Connector (WP10)

WordPress-side ReactWoo Cloud connectivity. **Never** called from the visitor page-render path.

## Responsibilities

- Secure pairing (one-time token → revocable `site_id` + `site_secret`)
- Encrypted credential storage (`rwgc_cloud_credentials`)
- Connection state (`disconnected` | `pairing` | `connected` | `error`)
- Manifest sync with revision / 304
- Atomic current + previous known-good manifests
- Heartbeat + capability / plugin reporting
- Disconnect / reconnect (disconnect does not destroy WP content or cached manifests)
- Hourly WP-Cron maintenance when connected

## API contract (Cloud v1)

Base: `https://cloud.reactwoo.com/api/v1` (filter: `rwgc_cloud_api_base`)

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/sites/pair` | pairing token in body | Exchange one-time token for site credentials |
| `POST` | `/sites/confirm` | `Bearer site_secret` | Confirm pairing |
| `GET` | `/sites/{site}/manifest` | Bearer + optional `If-None-Match` / `X-ReactWoo-Manifest-Revision` | Fetch compiled manifest (`304` if unchanged) |
| `POST` | `/sites/{site}/heartbeat` | Bearer | Liveness + local revision |
| `POST` | `/sites/{site}/capabilities` | Bearer | Capability + plugin inventory |
| `POST` | `/sites/{site}/migration/import` | Bearer | Import translated resources (`dry_run` does not persist; never sets `management_mode`) |
| `POST` | `/sites/{site}/management-mode` | Bearer | Explicit `{ "mode": "cloud" or "local" }` after import |

### Pair request body

```json
{
  "pairing_token": "…",
  "site_url": "https://example.com/",
  "site_name": "Example",
  "plugins": [{ "slug": "reactwoo-geocore", "version": "1.8.x", "active": true }],
  "core_version": "1.8.x"
}
```

### Pair response

```json
{
  "site_id": "site_…",
  "site_secret": "…",
  "api_base": "https://cloud.reactwoo.com/api/v1"
}
```

## Security

- No WordPress user passwords
- Site secret encrypted at rest (OpenSSL AES-256-CBC + `wp_salt`)
- Secret never shown in admin UI
- HTTPS required for API base (override only via `rwgc_cloud_allow_insecure_api_base` for local mocks)
- Manifests rejected when `site` ≠ stored `site_id`
- Invalid manifests leave previous known-good in place

## Helpers

```php
reactwoo_cloud_pair( $token );
reactwoo_cloud_is_connected();
reactwoo_cloud_sync_manifest(); // admin/cron only
reactwoo_cloud_get_manifest();  // local cache only — safe to read anywhere
reactwoo_cloud_record_event( $type, $attrs ); // local queue only — never HTTP
reactwoo_cloud_flush_events(); // admin/cron only
reactwoo_cloud_migration_preview(); // local only — never HTTP
reactwoo_cloud_import(); // admin only — backup + POST; does not switch mode
reactwoo_cloud_switch_management_mode( $mode ); // admin only — explicit after import
```

## Admin

Geo Core → **Cloud** — pair, sync now, disconnect, import preview, Import to ReactWoo Cloud, Switch to Cloud-managed.

Pairing always leaves `management_mode = local`. Disconnect keeps WordPress content, cached manifests, and `rwgc_cloud_migration_backup`.

## Out of scope (later packages)

- Cloud backend implementation (WP11) → **done:** sibling service `reactwoo-decision-cloud`
- Portal UI (WP12)
- Event batch upload queue on WP (Cloud accepts `/events/batch`; WP enqueue is follow-on) → **done WP14:** `RWGC_Cloud_Event_Queue` + `RWGC_Cloud_Telemetry` (cron/admin flush only)
- Existing customer migration (WP16) → **done:** explicit import + management-mode switch; connecting Cloud never mutates local config
