# Cursor output

## Status
done

## Task
WP13 Brand Profile + constrained Component Editor.

## Files changed
### reactwoo-decision-cloud
- Brand Profile GET/PUT, heartbeat `brand_hints`, manifest `brand_profile`
- Component schemas + preview HTML matching Geo Core WP8 renderer
- Portal Brand page + Component Editor in the experience wizard
- Tests in `tests/portal.test.js`

### reactwoo-geocore
- Presentation data attributes on component wrap
- Confirmed brand CSS variables from cached manifest (visitor path, no Cloud fetch)
- Heartbeat theme colour hints
- Version 1.8.131

## What was not changed
- No Stripe / WP15
- No visitor-time Cloud HTTP
- Gate D end-to-end (paired site publish → variant visible) not executed here

## Commands run
- `npm test` in decision-cloud
- `php tests/test-rwgc-components.php` in Geo Core
