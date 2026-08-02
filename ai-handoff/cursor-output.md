# Cursor output

## Status

**done** — Atomic Geo Visibility for Geo Core **v1.8.121**.

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-atomic-geo.php` — new Atomic bridge (`props-schema` + `controls` → Geo Visibility section)
- `includes/integrations/class-rwgc-integrations-loader.php` — load/init Atomic bridge
- `includes/targeting/class-rwgc-surface-settings.php` — unwrap Atomic `{$$type,value}` + boolean → `yes`/`''`; mirror library → applied id
- `includes/integrations/elementor/class-rwgc-elementor-frontend.php` — prefer `get_atomic_settings()`; nestable Atomic `should_render` hooks
- `reactwoo-geocore.php`, `readme.txt` — **1.8.121**
- `docs/TARGETING-RULES-PLAN.md` — Atomic dual-path note

## What was not changed

- Classic Advanced-tab registration (`RWGC_Elementor_Geo_Controls` / Elements hooks)
- reactwoo-atomic widgets (ownership stays in Geo Core)
- Full Select2 country UI / portable rule-builder textarea inside Atomic

## Commands run

- `php -l` on changed PHP files — pass

## Remaining (manual)

- Editor: Atomic Carousel/Off-Canvas show **Geo Visibility** under Settings; classic Advanced still works
- Frontend: country ISO string + Pro library rule hide/show; nestable Off-Canvas `should_render`; builder edit skips hide
