# Cursor output

## Status

**done** — Critical bug hunt on tip `87fb935` / v1.8.120. Fixed one NEW high-confidence critical (not covered by open PRs #1–#46).

## Bug and impact

Suite **Duplicate default page (recommended)** / `ai_adapt` create a country variant without Elementor document meta. Matching-country visitors can be routed to a draft/published page that has only `post_content`, while `_elementor_data` / `_elementor_edit_mode` stay empty — Elementor editor opens blank and builder frontend rendering breaks.

**Trigger:** Elementor-built master page → Suite Create page version → Duplicate default page (or AI adapt) → open/edit/publish the new variant.

## Root cause

`RWGC_Variant_Manager::create_country_variant()` only copied `$master->post_content`. No `rwgc_variant_created` listener compensated. Open PR #15 only restores the create-variant admin route.

## Fix

- After successful insert in duplicate mode, copy Elementor builder meta (`_elementor_data`, edit mode, version, template type, page settings, `_wp_page_template`).
- Strip `rwgc_route_*` keys from copied `_elementor_page_settings` so master Elementor SWITCHER values cannot override Suite variant routing meta (related to #45).
- Clear `_elementor_css` on the destination so CSS regenerates for the new post ID.
- Added `tests/Engine/RWGCVariantManagerElementorCopyTest.php`.

## What was not changed

- Blank / link-existing modes
- Open PRs #17/#18/#21/#25/#26/#28/#31/#33/#35/#37/#40–#46 (still unmerged)
- R2 latest publish workflow (no product correctness bug found)

## Commands run

- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Engine/RWGCVariantManagerElementorCopyTest.php` → OK (4 tests, 20 assertions)
