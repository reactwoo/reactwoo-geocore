# Cursor output

## Status

done

## Summary

Fixed Geo Assistant Google Ads mapping resolver so it treats Google Ads mapping separately from an already-valid URL condition in OR groups.

## Files changed

| File | Why |
|------|-----|
| `admin/js/rwgc-targeting-assistant.js` | Force standard UTM default; partition custom mapping; also-valid URL section; URL match editor; preserved selection when returning from URL edit |
| `admin/css/rwgc-targeting.css` | Styles for also-valid URL block, advanced custom section, URL match editor |
| `includes/class-rwgc-admin.php` | i18n for URL match flow and updated Google Ads copy |
| `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` | Structural guards for new resolver helpers |
| `reactwoo-geocore.php`, `readme.txt` | Version **1.8.95** |

## What was not changed

- Geo AI parser / planner / UTM resolver PHP.
- Popup target create/select REST flow.
- Executor OR-group semantics (client stores traffic mapping only; group structure unchanged).

## Commands run

- `node --check admin/js/rwgc-targeting-assistant.js` — pass

## Remaining

- Manual Local browser pass: open Google Ads OR URL group → confirm standard selected, URL shown valid, Apply → Create rule.
