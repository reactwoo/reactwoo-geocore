# Cursor output

## Status
done

## Task
Decision Cloud Sprint 6 — billing resilience.

## Files changed

### Decision Cloud (`0.15.0`)
- Added explicit ReactWoo.com reconciliation configuration and allowlisted HTTP client
- Added portal-authenticated manual reconcile endpoint with last-known snapshot fallback
- Added active, grace, paused, and last-known billing portal states
- Added Sprint 6 billing resilience coverage and health/version updates

### Geo Core docs
- Marked Sprint 6 complete in commerce/onboarding and work-package tracking
- Documented outage, `404`, local-only GET, public JSON, and portal acceptance

## What was not changed
- ReactWoo.com store plugin
- Site management mode
- Heartbeat or visitor paths
- Payment-gateway integrations

## Commands run
- Decision Cloud `npm test` — 85 passed, 0 failed

## Remaining
- Gate D / Gate E live-site validation remains
