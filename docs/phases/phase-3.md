# Phase 3 — Free UX (complete)

Aligned with `docs/geo-core-cursor-master-plan.md` §10 Phase 3.

## Delivered

- **`RWGC_Preview`** — `?rwgc_preview_country=XX` for trusted users; `rwgc_can_preview_geo` filter.
- **`[rwgc_if]`** — include/exclude/groups (evaluator-aligned); shortcode attributes remain **legacy** parseable format for backward compatibility.
- **§5.1 audit (Geo Core)** — Replaced free-typed ISO fields with prepopulated selects:
  - **Settings:** fallback country + currency use `RWGC_Admin::render_country_select()` / `render_currency_select()` (`RWGC_Countries::get_currency_options()`; WooCommerce currencies when available).
  - **Page meta (Geo Variant Routing):** secondary country is a `<select>` from `RWGC_Countries::get_options()`.
  - **Geo Content block:** `ComboboxControl` search + add, removable list — no comma-separated country entry.
- **Sanitization:** `RWGC_Settings` validates fallback country/currency against allowed option keys.
- **Docs:** Dashboard + Usage; shortcodes documented as legacy authoring where applicable.

## Hooks

| Hook | Purpose |
|------|---------|
| `rwgc_can_preview_geo` | Extend preview permission |
| `rwgc_geo_data` | Preview override (priority 999) |
| `rwgc_currency_options` | Filter currency dropdown list |

## Next phase

**Phase 4** — See `docs/phases/phase-4.md`.
