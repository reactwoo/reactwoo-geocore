# Cursor output

## Status
done

## Task
WP17 — Failure handling + health (shared status model, actionable remediation).

## Files changed

### Geo Core (`reactwoo-geocore` 1.8.153)
- `RWGC_Cloud_Health` snapshot: WP/PHP/Core/Woo/Elementor, heartbeat, manifest, queue, management mode
- Statuses: Healthy | Warning | Disconnected | Configuration Error
- Cloud admin health banner; `reactwoo_cloud_health()` (local, no HTTP)
- Heartbeat posts snapshot to Cloud
- Tests: `composer test:cloud-health`

### Decision Cloud (`reactwoo-decision-cloud` 0.8.0)
- `src/services/siteHealth.js` same evaluator codes/messages
- `GET /api/v1/sites/:id/health`; org site list + summary include health
- Portal top bar, Overview, Sites table use status labels
- Tests: `tests/health.test.js`

## What was not changed
- Visitor render path still has no Cloud HTTP
- Soft-fail sync/outage behaviour unchanged
- WP18 security pass not started

## Commands run
- Geo Core `php tests/test-rwgc-cloud-health.php` — pass
- Decision Cloud health + portal + migration tests — pass
- Billing/Paystack re-run after a Windows `spawn EPERM` flake — pass

## Remaining
- Gate D / Gate E still need a live site
- WP18 Security pass
