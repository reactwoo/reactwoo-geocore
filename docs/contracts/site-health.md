# Site health (WP17)

Shared structured health model for Geo Core and Decision Cloud. **Never** evaluated on the visitor page-render path.

Schema: `reactwoo.site_health/v1`

## Status

| Status | Label | Meaning |
|--------|-------|---------|
| `healthy` | Healthy | Paired, recent heartbeat, no blocking errors |
| `warning` | Warning | Connected but sync, queue, or heartbeat needs attention |
| `disconnected` | Disconnected | Not paired |
| `configuration_error` | Configuration Error | Credentials, API base, or Cloud-managed-without-manifest |

Issues always include `message` + `remediation` (plain English). Do not show opaque codes (`http_503`, `sync_failed`) as the only text.

## Snapshot shape

```json
{
  "schema": "reactwoo.site_health/v1",
  "status": "warning",
  "status_label": "Warning",
  "checked_at": "2026-08-14T09:00:00Z",
  "issues": [
    {
      "code": "heartbeat_stale",
      "severity": "warning",
      "message": "This site has not checked in with Cloud recently.",
      "remediation": "Open Geo Core → Cloud and click Sync now."
    }
  ],
  "environment": {
    "wordpress": "6.8",
    "php": "8.2.31",
    "geocore": "1.8.153",
    "woocommerce": "9.0",
    "elementor": "3.24",
    "plugins": [],
    "extensions": []
  },
  "connection": {
    "state": "connected",
    "management_mode": "local",
    "last_heartbeat_at": "",
    "manifest_revision": 1
  },
  "queue": { "pending": 0, "dropped": 0 },
  "capabilities": { "count": 12 }
}
```

Heartbeat stale after **2 hours**. Queue backlog warning at **100** pending events.

## WordPress

`reactwoo_cloud_health()` / `RWGC_Cloud_Health::snapshot()` — local only.

Heartbeat (cron/admin) posts the snapshot to Cloud. Failures stay soft; visitor rendering is unchanged.

## Cloud

- Heartbeat stores `connection.last_health`
- `GET /api/v1/sites/{site}/health`
- Organisation site list and summary include `health.status` / `status_label`
- Portal top bar, Overview, and Sites table use the four labels
