# Cursor output

## Status
done

## Task
1.8.143 — do not call get_widget_types() on bulk widgets-config.

## Files changed
- `class-rwgc-elementor-widgets-config.php` — return empty (`slim-empty`) without touching the widgets manager
- Version 1.8.143

## What was not changed
- PHP memory / timeout
- get_document_config (that request already completes)

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
