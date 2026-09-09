# Cursor output

## Status
done

## Task
Critical-bug hunt (cron). Stop Cloud re-pair from flushing another workspace's queued events.

## Files changed
- `includes/cloud/class-rwgc-cloud-event-queue.php` — stamp `site_id` on the durable queue; drop foreign items on persist/flush; `discard_unless_site()`
- `includes/cloud/class-rwgc-cloud-pairing.php` — discard a foreign/unstamped queue after a successful pair
- `tests/test-rwgc-cloud-events.php` — same-site reconnect keeps events; re-pair / credential change does not POST leftover purchases

## What was not changed
- Plugin version (1.8.163)
- Manifest leftover / `evaluate()` site check (open **#59**)
- Migration `is_imported()` ignoring stored `site_id` (admin workflow; not this PR)
- GeoIP forwarding-header trust (longstanding; needs trusted-proxy design)
- LiteSpeed vary (#55), Experience Slot #56/#57/#58

## Commands run
- `php tests/test-rwgc-cloud-events.php` — passed (including new re-pair assertions)
- `php tests/test-rwgc-cloud-connector.php` — passed
- `php tests/test-rwgc-cloud-security.php` — passed
- `php tests/test-rwgc-cloud-health.php` — passed
- `php tests/test-rwgc-cloud-migration.php` — passed

## Remaining errors
None for this fix.
