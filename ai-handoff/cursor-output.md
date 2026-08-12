# Cursor output

## Status
done

## Task
Elementor Elements panel spinning (widgets-config / suspected LiteSpeed 503) — Geo Core light path.

## Files changed
- `includes/integrations/elementor/class-rwgc-elementor-ajax.php` (new) — detect bulk `get_widgets_config` / `get_document_config`
- `includes/integrations/class-rwgc-integrations-loader.php` — load ajax helper
- `includes/integrations/elementor/class-rwgc-elementor-geo-controls.php` — empty country/library options + skip visitor preview on heavy AJAX
- `includes/integrations/elementor/class-rwgc-elementor-elements.php` — localise countries once for editor JS
- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php` — empty chips/library options on heavy AJAX
- `assets/js/rwgc-elementor-library-bridge.js` — hydrate Select2 countries from localised list
- `tests/test-rwgc-elementor-ajax.php` — smoke detector
- Version **1.8.128** (header, constant, readme)

## What was not changed
- WHMCS Bridge (already has stubs)
- Experience Slot controls (only container/section — small)
- No production deploy / tag / push
- Did not raise PHP memory/timeout

## Commands run
- `php tests/test-rwgc-elementor-ajax.php` — pass
- Full WP probe failed here (system PHP missing mysqli; use Local PHP)

## Remaining
- User should hard-refresh Elementor editor on the affected site and confirm Network: `elementor_ajax` / `get_widgets_config` → 200
- If still 503 with Geo Core disabled, isolate WHMCS / host again
