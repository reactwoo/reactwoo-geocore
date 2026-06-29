# Current task

> **Completed** in Geo Core **v1.8.92** (2026-06-29). Planner may replace this file for the next task.

## Problem

Popup target resolver start view was correct, but create flow did not reliably auto-select the new popup, modal appeared too low, buttons were cramped, and Remove action sat on the card edge.

## Expected

1. Create popup → auto-attach target to action → Resolution Hub updates → rule not created.
2. Centered viewport modal (`rwgc-modal-overlay` on `body`).
3. Consistent modal action/footer button layout.
4. Search only inside Choose existing popup.
5. Duplicate popup guard with Use existing / Create anyway.
6. Action Review Remove action in padded card footer.

## Acceptance test

- [x] Create/select sets target valid; hub shows remaining Google Ads mapping.
- [x] Modal centered; z-index above admin.
- [x] REST `attach_to_action`, `force_create`, duplicate response.
- [x] Structural PHPUnit guards.
- [ ] Manual browser pass on Local (planner/human).

## Do not touch

- Geo AI parser / planner interpretation logic.
- Generic Google Ads resolution drawer field logic (except shared modal shell).

## Cursor instructions

Task shipped — see `cursor-output.md`. Next task: replace this file.
