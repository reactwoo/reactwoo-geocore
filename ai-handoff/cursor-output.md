# Cursor output

## Status

**done** — NEW critical fixed as **PR #50** (`23c7133` on `cursor/critical-bug-investigation-bada`): Elementor `rwgc_route_*` overlays reclassifying Suite masters at tip `c50e0be`.

## Bug

When `_elementor_page_settings['rwgc_route_enabled'] === 'yes'`, `get_page_route_config()` overlaid Elementor `role` / `master_page_id` / `country_iso2` onto Suite post meta. A Suite master edited as Secondary in Elementor stopped loading Suite children (`Legacy_Route_Mapper` returns empty variants for `role=variant`). Stale Elementor country could also rebind legacy `country_page_id`.

Not covered by #45 (empty SWITCHER only), #47 (strip on variant copy), or #48 (same-country inline shadow).

## Fix

- `includes/class-rwgc-routing.php` — when post meta `_rwgc_route_enabled=1`, Elementor `yes` only reinforces enabled; role/country/master stay post-meta authoritative. Elementor-only pages (post meta not enabled) still overlay full fields.
- `tests/Engine/RWGCRoutingElementorOverlayTest.php` — regression coverage.

## Trigger

1. Suite → create FR variant for master M; publish.
2. Elementor on M → Enable Page Variant Routing On, role Secondary, set master+country; Update.
3. Before fix: FR visitor on M stays on master (Suite child ignored).
4. After fix: master role preserved; Suite child discovery works.

## Validation

`vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Engine/RWGCRoutingElementorOverlayTest.php` → OK (3 tests, 12 assertions)

## Checked / not re-reported

Gutenberg portable wipe (#46), visibility sanitize/ID (#17/#18), page-version query (#21), assistant REST (#28), Suite Elementor copy (#47), inline wipe/shadow (#48), empty SWITCHER (#45).
