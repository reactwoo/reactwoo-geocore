# Cursor output

## Status
done

## Task
Elements panel still HTTP 503 after 1.8.134. Omit Geo control trees from bulk widgets-config.

## Files changed
- `includes/integrations/elementor/class-rwgc-elementor-geo-controls.php` — skip entire Geo Visibility section on heavy ajax
- `includes/integrations/elementor/class-rwgc-elementor-elements.php` — same early return
- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php` — skip Atomic geo *controls* on heavy ajax; keep props schema
- `includes/integrations/elementor/class-rwgc-elementor-experience-slots.php` — skip slot section on heavy ajax
- Version 1.8.135

## What was not changed
- PHP memory / timeout (do not retry)
- WHMCS stubs
- Single-widget `editor_get_widget_config` still gets full controls
- Atomic props schema still registered (required so saved geo keys are not dropped)

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`

## Remaining errors
- Chrome `runtime.lastError` / “Receiving end does not exist” is a browser extension, not this 503.
