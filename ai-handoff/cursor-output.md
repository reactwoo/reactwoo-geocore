# Cursor output

## Status
done

## Task
1.8.146 — early-finish get_widgets_config before the rest of WordPress boots.

## Files changed
- `class-rwgc-elementor-ajax.php` — `early_widgets_config_responses()`
- `class-rwgc-elementor-widgets-config.php` — nonce-checked JSON exit (`slim-early`)
- Version 1.8.146

## What was not changed
- `get_document_config` still runs normally
- Hydrate still waits for panel/state-ready
- PHP memory / timeout

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
