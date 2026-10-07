# Cursor output

## Status
done

## Task
Critical-bug hunt on main after Geo Core 1.8.168 (`e5221ec`). Fix QUIC.cloud visitor IP when PHP’s TCP peer is a private reverse proxy.

## Files changed
- `includes/class-rwgc-geoip.php` — private/loopback `REMOTE_ADDR` now skips trusted-proxy hops in `X-Forwarded-For` (same walk as a public QUIC.cloud peer). If every public hop is a trusted proxy, the rightmost public hop is kept.
- `tests/test-rwgc-geoip-ip.php` — private peer + QUIC PoP, unchanged rightmost behavior when the checkbox is off, and trusted-CIDR skip behind a private peer.
- `CHANGELOG.md` — unreleased note. No version bump and no tag (`v1.8.168` is already on `e5221ec`).

## What was not changed
- Cloudflare still uses `CF-Connecting-IP` only when `REMOTE_ADDR` is a published Cloudflare range.
- Checkbox stays off by default. Public peers that are not trusted proxies still ignore forwarding headers.
- No LiteSpeed, Elementor, or visibility-rule changes.

## Commands run
- `php tests/test-rwgc-geoip-ip.php` — all assertions passed (PHP 8.3.6).

## Remaining errors
None in this test.
