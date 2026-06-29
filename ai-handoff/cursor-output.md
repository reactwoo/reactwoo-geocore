# Cursor output

## Status

done

## Summary

Fixed Create rule executing against stale Google Ads mapping state. Resolver changes now sync into the canonical proposal payload, execute collects resolutions by field (not exact raw key only), and blocked create responses name the unresolved field explicitly.

## Root cause

Google Ads mapping was stored in `state.cardResolutions` under a `raw` key that did not always match `requiredResolutions[].raw`, while `collectCardResolutions()` only exported exact matches. UI readiness used `findFieldResolution`-style logic via `effectiveRowStatus`, so the hub/card looked ready but execute sent an incomplete `resolutions` array to `geo-ai/v1/interpret/execute`.

## Files changed

| File | Why |
|------|-----|
| `admin/js/rwgc-targeting-assistant.js` | `findFieldResolution`, proposal sync, expanded `collectCardResolutions`, execute preflight, named blocked-create UI |
| `includes/class-rwgc-admin.php` | i18n for execute blocked messaging |
| `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` | Structural guards for execute sync helpers |
| `reactwoo-geocore.php`, `readme.txt` | Version **1.8.97** |
| `reactwoo-geo-ai/.../class-rwga-planner-action-card-builder.php` | `unresolved_field_details()` |
| `reactwoo-geo-ai/.../class-rwga-assistant-service.php` | Structured `unresolved_details` + `code: unresolved_fields` on reject |
| `reactwoo-geo-ai/.../class-rwga-card-resolution-applier.php` | Force-clear traffic unresolved on mapping choose |
| `reactwoo-geo-ai/tests/Services/RWGAAssistantExecuteValidationTest.php` | Assert structured unresolved details |
| `reactwoo-geo-ai/reactwoo-geo-ai.php`, `readme.txt` | Version **0.4.131** |

## What was not changed

- Geo AI parser / planner interpretation logic.
- Popup create/select flow and popup auto-match.
- Google Ads mapping drawer UI/cards.
- Country / device / page-type parsing.

## Commands run

- `node --check admin/js/rwgc-targeting-assistant.js` — pass

## Remaining

- Manual Local pass: apply Standard Google Ads UTM → Create rule succeeds.
- Install Geo Core **1.8.97** + Geo AI **0.4.131** together on Local.
- Release both repos when requested.
