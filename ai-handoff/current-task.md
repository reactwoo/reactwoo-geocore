# Current task

Verify and fix v1.8.106+ Rules page / Rule Tester styling on staging.

## Status

**done** (code fix in **v1.8.107**) — re-verify on staging after update.

## Root cause (suspected)

Rules/tester CSS depended on hook suffix containing `rwgc-visibility-rules` only. On some app-shell admin screens, enqueue could run before `rwgc-suite` was registered, leaving buttons/cards unstyled.

## Fix (v1.8.107)

- `RWGC_Visibility_Rule_Tester_Assets::is_visibility_rules_screen()` — hook + `$_GET['page']` + screen id + filter
- Enqueue priority 25 + `ensure_suite_styles()` bootstrap
- Modal dialog header/body/footer structure
- `.rwgc-btn` fallbacks in `rwgc-rules-page.css`

## Staging verification checklist

1. Plugins → Geo Core version **1.8.107**
2. Network: `rwgc-rules-page.css?ver=1.8.107` and `rwgc-rule-tester.css?ver=1.8.107` — 200
3. Targeting → Rules — card layout, pills, suite buttons
4. Test rule modal — wide two-column, country select, traffic presets
5. Hard refresh / purge optimisation cache if styles still stale
