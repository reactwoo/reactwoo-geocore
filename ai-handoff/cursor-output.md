# Cursor output

## Status
done

## Task
Production admin-ajax 503 persists after 1.8.137.

## Diagnosis
1.8.137 skipped `get_stack()` for add-on widgets but Unlimited Elements still registers every addon (`eval` + instantiate + DB preload) on `elementor/widgets/register` before that handler runs.

## Files changed
- `class-rwgc-elementor-widgets-config.php` — unhook heavy addon registrars at priority 0; Throwable guard; `X-RWGC-Widgets-Config` header
- Version 1.8.138

## What was not changed
- PHP memory / timeout
- Frontend UE widgets (only skipped during heavy editor AJAX)

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
