# Cursor output

## Status
done

## Task
1.8.141 — finish get_widgets_config before LiteSpeed 503 (time-box + skip heavy stacks).

## Files changed
- `class-rwgc-elementor-widgets-config.php` — request/stack budgets, progress checkpoints, skip Atomic/WHMCS/Pro Woo stacks
- `class-rwgc-elementor-config-debug.php` — trace only heavy elementor_ajax
- Version 1.8.141

## What was not changed
- PHP memory / timeout
- UE/ACPT unhook policy

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
