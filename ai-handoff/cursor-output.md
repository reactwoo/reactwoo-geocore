# Cursor output — Elementor empty routing switcher disables Suite redirects

## Status

**done** — fix on `cursor/critical-bug-investigation-8830`

## Bug and impact

After Suite creates a country variant (`_rwgc_route_enabled=1` on the master), opening the master in Elementor and updating (with **Enable Page Variant Routing** left Off / default) stores `rwgc_route_enabled: ""` in `_elementor_page_settings`. Frontend routing then treats the page as disabled, so matching-country visitors are not redirected. Suite/post meta still show routing enabled.

## Root cause

`RWGC_Routing::get_page_route_config()` treated Elementor SWITCHER empty string as an explicit disable, overriding Suite post meta.

## Fix

Honor Elementor only when `rwgc_route_enabled === 'yes'`. Empty/absent Elementor values defer to Suite/post-meta.

## Files changed

- `includes/class-rwgc-routing.php` — remove force-disable on empty Elementor switcher
- `tests/Engine/RWGCRoutingPageRouteConfigTest.php` — regression coverage

## Not changed

- IP header trust in `RWGC_GeoIP::get_current_ip()` (longstanding Cloudflare-oriented behavior; not fixed here)
- Open-PR topics #1–#44 (assistant REST, can_execute, Elementor wipe, preview isolation, etc.)

## Commands

```bash
php -l includes/class-rwgc-routing.php
php -l tests/Engine/RWGCRoutingPageRouteConfigTest.php
composer install --no-interaction --prefer-dist
vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Engine/RWGCRoutingPageRouteConfigTest.php
```
