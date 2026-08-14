# Cursor output

## Status
done

## Task
WP18 — Security pass (no features). Threat model + Critical/High fixes.

## Files changed

### Geo Core (`reactwoo-geocore` 1.8.154)
- Pairing ignores Cloud-returned `api_base`
- Site secrets: AES-CBC + HMAC (`v2.`), no plaintext fallback
- Block private/metadata Cloud API hosts
- Component renderer rejects `javascript:` and `//` URLs
- `docs/security/threat-model.md`
- Tests: `composer test:cloud-security`

### Decision Cloud (`reactwoo-decision-cloud` 0.9.0)
- Production portal/org/resource/billing require `PORTAL_API_TOKEN`
- `API_PUBLIC_BASE` used instead of `Host` in production
- Checkout return URLs allowlisted
- Pairing rate-limited; org/site mismatch rejected
- Protocol-relative / javascript URLs rejected in component preview
- Tests: `tests/security.test.js`

## What was not changed
- Per-user / per-org portal ACLs (Medium, accepted for v1)
- Visitor render path (still no Cloud HTTP)
- WP19 performance not started
- Gate D / Gate E still need a live site

## Commands run
- Geo Core cloud-security, components, cloud-connector — pass
- Decision Cloud security + api + portal + billing + paystack + health + migration — pass

## Remaining
- WP19 Performance pass
