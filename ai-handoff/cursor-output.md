# Cursor output

## Status
done

## Task
WP14 Events, goals, analytics.

## Files changed
### reactwoo-decision-cloud
- Validate `/events/batch` types; daily aggregates; Insights API; portal Insights page
- Reject unknown types (no session replay / heatmaps); hide uplift below 100 visitors

### reactwoo-geocore
- `RWGC_Cloud_Event_Queue` + `RWGC_Cloud_Telemetry` (local queue, cron/admin flush)
- Variant impression capture on slot render (no Cloud HTTP)
- Version 1.8.132

## What was not changed
- No Stripe / WP15
- No visitor-time Cloud HTTP
- Gate D / Gate E live site attribution not executed end-to-end

## Commands run
- `npm test` in decision-cloud
- `php tests/test-rwgc-cloud-events.php` in Geo Core
