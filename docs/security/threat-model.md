# Threat model (WP18)

Scope: Geo Core Cloud Connector + Decision Cloud control plane (WP10–WP17). Visitor page render must never call Cloud.

## Actors

| Actor | Trust |
|-------|--------|
| Site visitor | Untrusted |
| WordPress administrator | Trusted for that site |
| Paired WordPress site (site secret) | Trusted for that site only |
| Portal operator (portal token) | Trusted for this Cloud instance |
| Billing provider (ReactWoo.com WooCommerce) | Trusted after `X-WC-Webhook-Signature` verify |
| Network attacker | Untrusted |

## Assets

- Site secret (WordPress option, encrypted)
- Pairing tokens (15-minute, single use)
- Portal API token
- Compiled manifests
- Billing customer IDs / entitlements
- Event aggregates (no raw PII by contract)

## Statuses (WP18)

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| C1 | Critical | Unauthenticated portal, resource CRUD, org bootstrap, billing checkout | **Fixed** — `PORTAL_API_TOKEN` required in production (`REQUIRE_PORTAL_AUTH=1` or token set). Site Bearer routes unchanged. |
| C2 | Critical | Pairing `api_base` taken from Cloud JSON / `Host` header → credential theft | **Fixed** — WordPress stores only the locally configured API base. Cloud ignores `Host` when `API_PUBLIC_BASE` is set; production without it returns an empty base. |
| H1 | High | `https://` private/metadata hosts accepted as Cloud API base (SSRF) | **Fixed** — `RWGC_Cloud_Config::is_blocked_host()` unless the local insecure filter is on. |
| H2 | High | Credential option stored as AES-CBC without MAC (and plaintext base64 fallback) | **Fixed** — encrypt-then-MAC `v2.` blob; OpenSSL required; legacy CBC still decrypts. |
| H3 | High | Protocol-relative `//evil` and `javascript:` component URLs | **Fixed** — Cloud `sanitizeUrl` + PHP renderer `url()`. |
| H4 | High | Checkout `success_url` / `cancel_url` / `return_url` open redirect | **Fixed** — same-origin / `/portal` allowlist. |
| H5 | High | Resource upsert with `site_id` belonging to another organisation | **Fixed** — `organisation_mismatch` 403. |
| H6 | High | Pairing token spraying / confirm brute force | **Fixed** — 30 requests / 15 minutes per IP on `POST /sites/pair`. Tokens remain high-entropy. |
| M1 | Medium | Single portal token is instance-wide (no per-user / per-org ACLs) | Accepted for v1. Documented. Per-user auth is a later package. |
| M2 | Medium | CORS `*` in non-production | Accepted locally. Production disables wildcard unless `CORS_ORIGIN` is explicit. |
| M3 | Medium | Pairing tokens stored in plaintext in the JSON store | Accepted: single-use, 15-minute TTL, hashed site secrets. |
| M4 | Medium | Event batch poisoning by a stolen site secret | Accepted: site auth required; types validated; no visitor PII in payload contract. |
| M5 | Medium | SHA-256 site secret hashes without pepper | Accepted: 32-byte random secrets. |
| L1 | Low | `/health` unauthenticated liveness | Accepted. |
| L2 | Low | Admin Cloud UI capability + nonce already in place | No change. |

## Controls that already held

- Site routes: Bearer + SHA-256 + timing-safe compare
- Pairing tokens: 15-minute TTL, consume-on-use
- WooCommerce webhooks: HMAC-SHA256 + missing-secret reject + delivery-id idempotency
- Manifest fetch: site auth; visitor render uses local cache only
- WP Cloud admin: `manage_options` + `check_admin_referer`
- Component HTML: `esc_html` / `esc_url` / `esc_attr`
- JSON body limit 1mb; event batch max 500

## Production checklist

1. `NODE_ENV=production`
2. `PORTAL_API_TOKEN` set (long random)
3. `API_PUBLIC_BASE=https://…/api/v1`
4. `CORS_ORIGIN` explicit if the portal is ever cross-origin
5. `ALLOW_DEV_PAIRING` unset
6. `WOOCOMMERCE_WEBHOOK_SECRET` and `REACTWOO_HANDOFF_SECRET` set; `REACTWOO_STORE_ORIGIN` is the ReactWoo.com origin
7. Geo Core API base is HTTPS and not a private host
