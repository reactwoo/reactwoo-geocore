# Cursor output — visibility rule logic wording + preview/test panel

## Status

**done** (needs local UI verification on visibility rule edit screen)

## Task

Clarify AND/OR wording in the rule editor and add human-readable logic preview + scenario test panel for visibility rules (Free Delivery fixture). No Geo AI parser/planner changes.

## Files changed

| File | Why |
|------|-----|
| `assets/js/rwgc-rule-builder.js` | Card view: "All condition groups must match", OR chip separators, OR in summaries, traffic card operator cleanup |
| `includes/class-rwgc-targeting-rule-builder-assets.php` | i18n (`matchAllGroups`, card summaries, `orSeparator`); enqueue `rwgc-visibility-rule-preview.js` + REST localize |
| `admin/js/rwgc-visibility-rule-preview.js` | Live logic preview refresh + test form POST to REST |
| `admin/css/rwgc-rule-editor.css` | Logic preview list, test result states, OR chip style |
| `admin/views/visibility-rules-edit.php` | Logic preview card, test panel, `data-rwgc-target-label` on form |
| `includes/class-rwgc-visibility-rule-logic-preview.php` | **NEW** — numbered AND/OR logic copy |
| `includes/class-rwgc-visibility-rule-preview.php` | **NEW** — scenario snapshot + evaluator bridge |
| `includes/class-rwgc-visibility-rule-editor-presenter.php` | `logic_preview` in presenter output |
| `includes/class-rwgc-rest.php` | `POST /targeting/preview-rule` |
| `includes/class-rwgc-plugin.php` | require new classes |
| `includes/targeting/class-rwgc-rule-evaluator.php` | Core UTM resolvers; map `is`/`is_not` + array values to `in`/`not_in` for attribution fields |
| `tests/Targeting/RWGCVisibilityRulePreviewTest.php` | **NEW** — 7 Free Delivery scenarios + logic preview branches |

## What was not changed

- Geo AI planner/parser/converter/executor
- Rule storage schema (compatible with existing portable JSON)
- MaxMind / satellite plugins

## Commands run

- CLI evaluator check (7 Free Delivery cases): **all OK** after UTM `is`+array fix
- `composer test -- --filter RWGCVisibilityRulePreviewTest`: **blocked** — Windows PHPUnit `TextUI\Command` not found (known issue)

## Acceptance mapping

1. Top-level label → **All condition groups must match** (card view + help text)
2. Country include → **Visitor country is any of:** + **Ireland OR United Kingdom** chips
3. Country exclude → **Visitor country is not any of:** + **France OR Germany**
4. Traffic → **Match any of:** with OR branches
5. Google Ads branch → **utm_source=google AND utm_medium=cpc** in branch lines / preview children
6. Preview logic → numbered list with traffic sub-bullets
7. Test panel → REST `preview-rule` with country/device/page/URL/UTM inputs
8. Parser/planner → unchanged
9. Schema → unchanged

## Remaining / manual

- Open **Targeting → Visibility rules → Edit** Free Delivery rule on Local (Geo Core ≥ uncommitted + Geo AI ≥ 0.4.134)
- Confirm card summaries, preview logic refresh on edit, and test panel match/no-match copy
- Release as **v1.8.103** when ready (not tagged in this pass)
