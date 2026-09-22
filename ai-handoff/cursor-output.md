# Cursor output

## Status
done

## Task
Stop Elementor builder spinner caused by leftover `$heavy` in document Geo Visibility.

## Files changed
- `includes/class-rwgc-elementor.php` — load library options via `RWGC_Elementor_Options` (no `$heavy`)
- `includes/integrations/elementor/class-rwgc-elementor-geo-controls.php` — memoize visitor preview once per request
- Tests pin `$heavy` stays gone
- Version **1.8.166**

## What was not changed
- Returning-visitor cookies / evaluators (not on the widgets-config path)
- No `get_widgets_config` override, no add-on unhooking
- Country SELECT2 still registered on widget stacks (1.8.150 contract)

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`

## Remaining errors
None for this fix.
