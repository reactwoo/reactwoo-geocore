# Cursor output — rules list + rule tester UI modernisation

## Status

**done** (needs local UI verification)

## Task

Modernise Targeting Rules list and Rule Tester modal UI. UI/UX only — no evaluator/parser/storage contract changes.

## Files changed

| File | Why |
|------|-----|
| `admin/views/visibility-rules-list.php` | Card sections, pills/chips, header actions, empty states, duplicate action |
| `admin/css/rwgc-rules-page.css` | **NEW** — scoped rules page table/card/chip styles |
| `admin/views/visibility-rule-tester-modal.php` | Wider modal shell classes |
| `admin/css/rwgc-rule-tester.css` | Two-column modal, sections, result badges, modal buttons |
| `admin/js/rwgc-visibility-rule-tester.js` | Guided tester layout, searchable selects, traffic presets, validation |
| `includes/class-rwgc-visibility-rule-tester-assets.php` | Rules CSS enqueue, expanded labels, suite deps |
| `includes/class-rwgc-visibility-rule-tester.php` | Bootstrap `countries`; rule payload `included_countries` / `excluded_countries` |
| `includes/class-rwgc-admin-visibility-rules.php` | `handle_duplicate` admin action for list Duplicate button |
| `reactwoo-geocore.php` / `readme.txt` | **v1.8.106** |

## What was not changed

- Geo AI parser/planner
- Rule storage schema
- Evaluator contracts
- Popup / Google Ads resolvers
- MaxMind handling

## Manual checks

1. Rules list → card layout, chips, pills, empty states
2. Row **Test** preselects rule; header **Test rule** opens blank picker
3. Editor **Test rule** still opens modal with current rule
4. Country dropdown shows readable names; submits ISO
5. Traffic presets fill Google Ads / winter-sale / clear campaign
6. **Run test** disabled until rule + country + device + page type set
7. Result panel shows MATCH / NO MATCH with condition bullets

## Commands

```bash
npm run package:zip
```

## Release

Bump shipped as **v1.8.106** — commit/tag/push when user requests release.
