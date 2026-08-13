# Cursor output

## Status
done

## Task
1.8.142 — skip all get_stack() when widgets-config boot is already late.

## Files changed
- `class-rwgc-elementor-widgets-config.php` — late-boot empty stacks, WHMCS unhook, per-widget progress
- Version 1.8.142

## What was not changed
- PHP memory / timeout

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
