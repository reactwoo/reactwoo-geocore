# Cursor output

## Status
needs-review (production verification pending)

## Task
1.8.150 — remove the cross-plugin Elementor widget-loading workarounds and restore native
widget registration. Optimise only work owned by Geo Core.

## Files changed
- `includes/integrations/elementor/class-rwgc-elementor-ajax.php` — reduced to `is_elementor_ajax()`; no heavy / hydrate / constrained classification
- `includes/integrations/elementor/class-rwgc-elementor-widgets-config.php` — **deleted** (widgets-config override, registrar unhooking, early AJAX finish, `rwgc_get_widget_config`)
- `assets/js/rwgc-elementor-widget-hydrate.js` — **deleted** (wrapped `getElementData` / `$e.run`, seeded fake `tabs_controls`)
- `includes/integrations/elementor/class-rwgc-elementor-config-debug.php` — **deleted**, replaced by `class-rwgc-elementor-profiler.php` (opt-in, ReactWoo callbacks only)
- `includes/integrations/elementor/class-rwgc-elementor-options.php` — **new**: memoized, bounded providers for countries, visibility library rows/select/chips, master pages and the visitor preview
- `includes/class-rwgc-plugin.php` — services no longer gated on a heavy/constrained editor request; only admin-screen bootstrap still defers during `elementor_ajax`
- `includes/class-rwgc-elementor.php`, `.../class-rwgc-elementor-elements.php`, `.../class-rwgc-elementor-geo-controls.php`, `.../class-rwgc-elementor-atomic-geo.php`, `.../class-rwgc-elementor-experience-slots.php` — heavy-AJAX gates removed; option lookups delegate to `RWGC_Elementor_Options`
- `includes/targeting/class-rwgc-rule-registry.php` — debug logger swapped for the opt-in profiler
- `tests/test-rwgc-elementor-ajax.php` — rewritten for the new surface

## What was not changed
Nothing in the Geo Visibility runtime. Untouched: `RWGC_Rule_Evaluator` and the targeting
stack, `RWGC_Elementor_Frontend` (`should_render`, `before_render`, wrapper classes, Atomic
nestable discovery, inline hidden-element CSS), `RWGC_Elementor_Popups` (all `wp_head` /
`wp_footer` / `should_show` / wrapper-attribute handling), `RWGC_Elementor::filter_document_content`,
`assets/js/frontend.js`, Experience Slots render hooks and `after_editor_save`, every saved
control key, and the Atomic Props schema.

## Do not retry
- Replacing `get_widgets_config` / `refresh_widgets_config`
- Removing another plugin's callbacks from `elementor/widgets/register` or `elementor/controls/register`
- Finishing an Elementor AJAX request early from `plugins_loaded`
- Custom per-widget inspector hydration or synthesising `tabs_controls`

These are what produced the empty inspector and the `[object Object]` tabs.

## Commands run
- `php tests/test-rwgc-elementor-ajax.php` — 35 assertions OK
- All `tests/test-*.php` — pass except the pre-existing `test-rwgc-rule-evaluator.php`
  failure ("Hide mode should suppress when rule matches"), which loads only targeting
  files that were not modified here

## Watch on production
Set `define( 'RWGC_ELEMENTOR_PROFILE', true );`, open the editor, then read the
`[RWGC_EL_PROFILE]` line or the `rwgc_elementor_profile_last` option. Every row should show
`http: 0`, and each `RWGC_Elementor_Options::*` key should appear once per request.
