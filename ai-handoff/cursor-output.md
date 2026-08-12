# Cursor output

## Status

done

## Summary

Critical hunt on tip `c50e0be`: found LiteSpeed cache vary trusting client `rwgc_cc` / `rwgc_pv` cookies (first-visit shared empty-cookie bucket + forged-cookie poison). Fixed `RWGC_Cache_Compat` to derive LiteSpeed vary groups from server-side GeoIP / Page Version context, always sync cookies to that truth, and stop registering those cookies as `litespeed_vary_cookies`.

## Files changed

| File | Why |
|------|-----|
| `includes/integrations/class-rwgc-cache-compat.php` | Server-side LiteSpeed vary; cookie sync overwrite; drop client-controlled vary cookies |
| `tests/Integrations/RWGCCacheCompatTest.php` | Regression coverage for vary group helpers |

## Not changed

- Open PR topics #1–#54 (visibility save, page version spoof, MaxMind wipe, recursion OOM, popups, assistant REST, rule tester, Insights AI, Suite overlays, Atomic chips/hide_if, R2 latest, Gutenberg meta, explicit OFF leftovers, GeoIP XFF, Variant Manager IDOR)
- WooCommerce product direct-URL purchase eligibility (Geo Commerce owns that surface)
- Provenance `infer_provenance_from_rule_set` wrong JSON shape (admin-only; `mark_variant_archived` has no callers)

## Commands run

- `php -l includes/integrations/class-rwgc-cache-compat.php` → OK
- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Integrations/RWGCCacheCompatTest.php` → OK (4 tests, 10 assertions)

## Remaining errors

None for this fix.
