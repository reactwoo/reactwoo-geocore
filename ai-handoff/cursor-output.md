# Cursor output

## Status
done

## Task
Stop Elementor country hydration from clearing saved countries and variant routing.

## Files changed
- `assets/js/rwgc-elementor-library-bridge.js` — restore the Elementor model value when filling an empty country SELECT2, and do not fire a bare `change` (that wrote `[]` / `''` back through `document/elements/settings`). Apply the same catalogue to `elementor.widgetsCache` after `get_widgets_config`, including Atomic chips.
- `tests/test-rwgc-elementor-ajax.php` — lock the hydrate script so it cannot emit a model-writing `change`.
- `tests/test-rwgc-country-hydrate.js` — lock value normalization and catalogue fill.
- Version **1.8.168**

## What was not changed
- Country controls still ship with empty PHP options (shared catalogue).
- No `get_widgets_config` override, no add-on unhooking.
- Returning-visitor cookies, decision runtime, and frontend evaluators.

## Commands run
- `node --check assets/js/rwgc-elementor-library-bridge.js`
- `node tests/test-rwgc-country-hydrate.js` — pass
- `php tests/test-rwgc-elementor-ajax.php` — pass

## Remaining errors
None for this fix.
