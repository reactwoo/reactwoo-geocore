# Cursor output

## Status
done

## Task
1.8.140 — stop 1.8.139 debug storm; unhook ACPT; treat font enqueue as light.

## Files changed
- `class-rwgc-elementor-config-debug.php` — boot / log / option write only on `elementor_ajax`
- `class-rwgc-elementor-widgets-config.php` — ACPT registrar unhook; skip widgets_registered log off ajax
- `class-rwgc-elementor-ajax.php` — `enqueue_google_fonts` (and similar) are light
- Version 1.8.140

## What was not changed
- PHP memory / timeout
- Geo Visibility option slimming
- UE skip-stack policy

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
