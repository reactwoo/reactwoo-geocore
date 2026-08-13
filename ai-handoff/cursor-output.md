# Cursor output

## Status
done

## Task
1.8.149 — make single-widget hydrate actually return third-party control stacks; fix `[object Object]` inspector tabs.

## Files changed
- `class-rwgc-elementor-widgets-config.php` — hydrate keeps every add-on registrar hooked; full stack limited to the requested widget + `common` / `common-optimized`; stub tabs from `Controls_Manager::get_tabs()`; `ajax_single_widget` logs per-key control counts
- `rwgc-elementor-widget-hydrate.js` — tab labels are strings, object-shaped tabs normalized to their title, hydrate also fires from the `panel/editor/open` command
- `tests/test-rwgc-elementor-ajax.php` — cover `hydrate_widget_name()` in place of the removed UE-registrar helpers
- Version 1.8.149

## What was not changed
- `slim-early` bulk widgets-config (still the LiteSpeed 503 mitigation)
- Heavy path still unhooks UE / ACPT / WHMCS registrars

## Commands run
- `php tests/test-rwgc-elementor-ajax.php` — 50 assertions OK

## Watch on production
- `ajax_single_widget` `counts` should show `ucaddon_…:<n>` with n > 0
- A hydrate `boot` with no `ajax_single_widget` means the UE preload 503'd that request
