# Cursor output — Critical bug fix (popup retarget execute mismatch)

## Status

**done** — fix committed on `cursor/critical-bug-investigation-8951`

## Bug and impact

Changing a popup target in Geo Assistant Action Review could leave the Create rule execute payload attached to the previously matched popup while the UI showed the newly chosen one. Admins could silently create a visibility rule on the wrong popup.

## Root cause

`seedPopupTargetCardResolutions()` only filled missing `cardResolutions` keys. Auto-match seeded every raw alias (including `requiredResolutions[].raw`). Change popup wrote only one alias (`target.raw` / button `data-raw`), so collect/export still preferred the stale required-resolution key. The mismatch guard only checked that *some* target id was exported.

## Fix

- Clear all `target` resolution keys for the card, then overwrite every raw alias on seed/retarget
- Prefer `targetFieldRaw()` when opening the popup resolver and rendering Change popup
- Preserve create metadata (`created_by_assistant`, etc.) when reseeding
- Harden `executePayloadTargetMismatches()` to require exported id === visible `card.target.resolved.id`

## Files changed

- `admin/js/rwgc-targeting-assistant.js`
- `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php`

## What was not changed

- `can_execute` fail-open for action cards (already covered by open PR #33)
- REST `edit_pages` vs `can_manage` (already covered by open PR #28)
- Signed preview isolation / Elementor assignment loss (PRs #40–#42)
- Version bump / tagged release

## Commands run and results

- `node --check admin/js/rwgc-targeting-assistant.js` — OK
- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Admin/RWGCTargetingAssistantUiRegressionTest.php --filter test_popup_retarget_overwrites_all_target_resolution_aliases` — OK (1 test, 16 assertions)

## Remaining errors

None for this fix. Other known critical items remain in existing draft PRs (#28, #33, #40–#43).
