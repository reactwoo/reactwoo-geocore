# Cursor output

## Status

done

## Summary

Fixed three remaining Geo Assistant UX/state issues for the Free Delivery popup rule journey: popup auto-match, Action Review Create rule footer, and Resolution Hub ready label.

## Files changed

| File | Why |
|------|-----|
| `admin/js/rwgc-targeting-assistant.js` | Pass popup registry in interpret context; client auto-match with suffix normalization; popup resolved UI (Set to / Change popup); Action Review footer with Edit rule + Create rule; setupStatusLabel shows Ready to create when resolved |
| `admin/css/rwgc-targeting.css` | Footer left/right layout for action cards |
| `includes/class-rwgc-admin.php` | Load popups via target service (pending + private filter); i18n cardChangePopup, statusReadyManual |
| `includes/targeting/class-rwgc-assistant-target-service.php` | normalize_popup_label(); improved find_similar_popups(); private post filter in search |
| `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` | Guards for auto-match helpers, footer buttons, ready status label |
| `tests/Targeting/RWGCAssistantTargetServiceTest.php` | normalize_popup_label tests |
| `reactwoo-geocore.php`, `readme.txt` | Version **1.8.96** |

## What was not changed

- Geo AI parser / planner interpretation logic.
- Popup create REST flow.
- Google Ads mapping resolver logic (v1.8.95 behaviour preserved).
- Country / device / page-type parsing.

## Commands run

- `node --check admin/js/rwgc-targeting-assistant.js` — pass
- `composer test --filter RWGCTargetingAssistantUiRegressionTest|RWGCAssistantTargetServiceTest` — blocked (PHPUnit\TextUI\Command not found on Windows agent; see known-issues.md)

## Remaining

- Manual Local browser pass per acceptance test (Free Delivery auto-match, ready footer, hub label).
- Release: commit, tag v1.8.96, push, package zip when requested.
