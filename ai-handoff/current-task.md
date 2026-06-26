# Current task

> **Completed** in Geo Core **v1.8.91** (2026-06-26). Planner may replace this file for the next task.

## Problem

Geo Assistant **Resolve popup target** modal was confusing: existing popups, Search, Choose popup, and Remove action all appeared as top-level buttons.

## Expected

1. Start view: Create new popup | Choose existing popup | Remove action | Cancel only.
2. Create flow: prefilled name, draft status, attach checkbox, `POST /targets/create`, update action target without creating rule.
3. Choose flow: debounced search, radio rows, Use selected popup (disabled until selection), empty state.
4. Remove flow: confirmation before discard.
5. Consistent `.rwgc-button` modal styling.

## Acceptance test

- [x] Start view shows only three primary choices + Cancel (no popup names as buttons).
- [x] Search only inside Choose existing popup.
- [x] Create/select sets target valid; Resolution Hub updates; rule not auto-created.
- [x] Remove requires confirmation.
- [x] REST `targets/search` and `targets/create` registered.
- [ ] Manual browser pass on Local (planner/human).

## Do not touch

- Geo AI parser / planner interpretation logic.
- Generic Google Ads resolution drawer (non-popup fields).

## Cursor instructions

Task shipped — see `cursor-output.md`. Next task: replace this file.
