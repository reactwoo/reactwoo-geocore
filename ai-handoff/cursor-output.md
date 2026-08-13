# Cursor output

## Status
done

## Task
1.8.144 — on-demand widget inspector hydrate after slim-empty bulk config.

## Files changed
- `class-rwgc-elementor-ajax.php` — `rwgc_get_widget_config` is light; `is_widget_hydrate_ajax()`
- `class-rwgc-elementor-widgets-config.php` — register hydrate action, enqueue JS, unhook UE on hydrate, full stack for one widget
- `class-rwgc-elementor-config-debug.php` — trace hydrate requests
- `assets/js/rwgc-elementor-widget-hydrate.js` — fetch + `addWidgetsCache` + re-open panel
- Version 1.8.144

## What was not changed
- PHP memory / timeout
- Bulk `get_widgets_config` still returns empty (`slim-empty`)
- `get_document_config`

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`

## Remaining
- After deploy: panel still loads; selecting Heading/Button fills inspector via `rwgc_get_widget_config`
- If hydrate 503s, leftover 112 Pro modules on `get_widget_types()` is the next target
