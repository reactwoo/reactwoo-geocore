# Cursor output

## Status

**done** — Atomic Geo Visibility registration fix for Geo Core **v1.8.123**.

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php` — register on `elementor/init` + `e_atomic_elements` experiment (was `elementor/loaded` + `class_exists(..., false)`, which never attached filters); inject Enable country / visibility rules into General `settings`; fallback sibling section
- `reactwoo-geocore.php`, `readme.txt` — **1.8.123**
- `docs/TARGETING-RULES-PLAN.md` — Atomic General injection note

## What was not changed

- Classic Advanced-tab registration
- Frontend evaluator / nestable `should_render` (already in 1.8.121)
- Full Select2 country UI inside Atomic

## Root cause

`atomic_api_available()` used `class_exists( …, false )` on `elementor/loaded` before Atomic classes exist, so props-schema/controls filters were never added.

## Commands run

- `php -l` pending

## Remaining (manual)

- Editor: select Flexbox / Div → General → Enable country targeting (+ Pro Enable visibility rules)
