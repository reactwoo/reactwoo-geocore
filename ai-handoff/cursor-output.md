# Cursor output

## Status
done

## Task
Production Elements panel still spins. Latest console 503 is `enqueueFont` → `sendBatch` on `admin-ajax.php`.

## Diagnosis
`enqueueFont` with `immediately=true` flushes Elementor’s pending AJAX batch, which still includes `get_widgets_config`. Same LiteSpeed 503 as before. Elementor 4.2 has no `editor_get_widget_config`. Bulk config still runs `get_stack()` for every third-party widget.

## Files changed
### reactwoo-geocore 1.8.137
- `includes/integrations/elementor/class-rwgc-elementor-widgets-config.php` — replace bulk widgets-config; skip add-on `get_stack()`; slim large option maps
- `includes/class-rwgc-plugin.php` — always init the wrapper (including heavy path)
- Tests for skip/slim helpers

### reactwoo-geo-optimise 0.4.93
- Skip goal + page-goal control injection on heavy Elementor AJAX

## What was not changed
- PHP memory / timeout (do not retry)
- Elementor core / Pro / Atomic / ReactWoo widget stacks (still built, options capped)

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
