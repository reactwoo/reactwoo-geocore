# Cursor output

## Status
done

## Task
1.8.145 — stop hydrate from 503ing the Elements panel.

## Files changed
- `rwgc-elementor-widget-hydrate.js` — wait for `panel/state-ready`, send immediately (not batched)
- `class-rwgc-elementor-ajax.php` — `is_constrained_elementor_ajax()`
- `class-rwgc-plugin.php` — skip Cloud/integrations on hydrate; still init `RWGC_Elementor`
- `class-rwgc-elementor-config-debug.php` — boot log includes action/heavy/hydrate
- Version 1.8.145

## What was not changed
- Bulk `get_widgets_config` still `slim-empty`
- PHP memory / timeout

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
