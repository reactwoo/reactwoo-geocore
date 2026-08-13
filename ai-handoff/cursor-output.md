# Cursor output

## Status
done

## Task
Fix Elementor Elements panel spinning again after 1.8.133.

## Files changed
- `includes/platform/class-rwgc-wp-abilities-adapter.php` — skip category/ability registration on `elementor_ajax` so `_doing_it_wrong` HTML cannot corrupt widget-config JSON
- `includes/class-rwgc-elementor.php` — document Geo Visibility uses the same heavy-path empty country/library/page lists as widget stacks
- `tests/test-rwgc-elementor-ajax.php` — assert abilities skip on `elementor_ajax` only
- Version 1.8.134

## What was not changed
- 1.8.128 widget-stack country/library slim (`RWGC_Elementor_Ajax::is_heavy_elementor_ajax`)
- PHP memory / timeout (do not retry)
- WHMCS Bridge stubs
- Cloud visitor-path HTTP (still none)

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
- `php tests/test-rwgc-platform-capabilities.php`

## Remaining errors
- None locally. Production needs 1.8.134 installed; confirm `POST admin-ajax.php` `elementor_ajax` / `get_widgets_config` is HTTP 200 JSON.
