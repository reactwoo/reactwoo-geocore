# Cursor output — visibility rule tester modal UX

## Status

**done** (needs local UI verification on Rules list + rule editor)

## Task

Move the inline rule test panel into a decoupled modal CTA with rule, content, and visitor context steps. Keep logic preview in the editor.

## Files changed

| File | Why |
|------|-----|
| `includes/class-rwgc-visibility-rule-tester.php` | **NEW** — bootstrap, rule payload, content merge, presets, run orchestration |
| `includes/class-rwgc-visibility-rule-tester-assets.php` | **NEW** — enqueue modal JS/CSS + localize bootstrap |
| `includes/class-rwgc-visibility-rule-preview.php` | `evaluate_detailed()` with per-condition pass/fail explanations |
| `includes/class-rwgc-visibility-rule-logic-preview.php` | `build_compact()` + public `is_google_ads_branch()` for modal conditions |
| `includes/class-rwgc-rest.php` | Extended `POST /targeting/preview-rule`; added `GET /targeting/rule-tester/rule/{id}` |
| `includes/class-rwgc-plugin.php` | require + init tester assets |
| `admin/js/rwgc-visibility-rule-tester.js` | **NEW** — modal UI, presets, run test |
| `admin/js/rwgc-visibility-rule-preview.js` | Logic preview only (removed inline test form) |
| `admin/css/rwgc-rule-tester.css` | **NEW** — modal + result styles |
| `admin/views/visibility-rule-tester-modal.php` | **NEW** — modal shell |
| `admin/views/visibility-rules-edit.php` | Removed inline test panel; `Test rule` CTA in header + sidebar |
| `admin/views/visibility-rules-list.php` | Top-level + row `Test` buttons |
| `includes/class-rwgc-targeting-rule-builder-assets.php` | Trimmed preview script i18n |

## What was not changed

- Geo AI parser/planner
- Rule storage schema
- Evaluator contracts (`RWGC_Rule_Evaluator` unchanged)
- Popup / Google Ads resolvers

## Manual checks

1. Rules list → **Test rule** opens modal with rule picker
2. Row **Test** preselects that rule
3. Rule editor → **Test rule** preselects current rule; unsaved textarea JSON is used
4. Content picker fills page type + URL from page/product
5. Presets appear for Free Delivery–style rules
6. Run test → Match / No match with bullet explanations
7. Logic preview card still refreshes on edit

## Release

Not tagged in this pass — bump **v1.8.104** when ready.
