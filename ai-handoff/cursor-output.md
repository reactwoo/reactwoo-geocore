# Cursor output

## Status

done

## Summary

Replaced the cramped Google Ads mapping resolver button row with stacked radio option cards, friendly labels, separated danger remove action, and inline custom mapping input. Version **1.8.94**.

## Files changed

| File | Reason |
|------|--------|
| `admin/js/rwgc-targeting-assistant.js` | `renderGoogleAdsMappingDrawer`, mapping meta labels, child display, no `window.prompt` |
| `admin/css/rwgc-targeting.css` | `.rwgc-mapping-*` card layout + condition child name/value |
| `includes/class-rwgc-admin.php` | Google Ads mapping i18n strings |
| `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` | Google Ads resolver structural tests |
| `reactwoo-geocore.php`, `readme.txt` | Version **1.8.94** |
| `ai-handoff/current-task.md`, `cursor-output.md` | Handoff |

## What was not changed

- Geo AI planner / UTM option keys (`utm_source_google_and_medium_cpc`, etc.).
- Popup target resolver and target create REST.
- Parser tests in Geo AI.

## Commands run

```bash
composer test -- --filter RWGCTargetingAssistantUiRegressionTest
# Not run — known PHPUnit env issue on Windows agent shell
```

## Remaining errors

None in code. Manual Local UI pass recommended.

## Release

Not run — awaiting user commit/tag/push request.
