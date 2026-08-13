# Cursor output

## Status
done

## Task
Add Elementor widgets-config debug logging to track ReactWoo entries on the still-failing 503.

## Files changed
- `class-rwgc-elementor-config-debug.php` — checkpoints, shutdown flush, headers, last snapshot option
- `class-rwgc-elementor-widgets-config.php` — log unhooks, leftover registrars, our/slow widgets
- Version 1.8.139

## What was not changed
- PHP memory / timeout
- Widget skip/unhook policy

## Commands run
- `php -l` on debug + widgets-config
- `php tests/test-rwgc-elementor-ajax.php`
