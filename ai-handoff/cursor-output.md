# Cursor output

## Status

**done** — Atomic country chips (selectable list) for Geo Core **v1.8.124**.

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php` — `egp_countries` is `String_Array_Prop_Type` + `Chips_Control` from canonical country options (no ISO CSV text)
- `includes/targeting/class-rwgc-surface-settings.php` — recursive Atomic unwrap + normalize country codes to ISO2 array
- `includes/targeting/class-rwgc-targeting-surface-evaluator.php` — parse nested Atomic string items
- `includes/integrations/elementor/class-rwgc-elementor-frontend.php` — delegate parse to surface evaluator
- `reactwoo-geocore.php`, `readme.txt` — **1.8.124**
- `docs/TARGETING-RULES-PLAN.md` — chips note

## What was not changed

- Classic Select2 / native country UI on Advanced tab
- Pro visibility-rule library Select

## Commands run

- pending lint / release

## Remaining (manual)

- Editor: Flexbox/Div → General → Enable country targeting → searchable country chips (not free-typed ISO)
