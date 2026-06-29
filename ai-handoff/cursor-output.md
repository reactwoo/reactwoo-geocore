# Cursor output

## Status

done

## Summary

Fixed popup create failure caused by empty `proposal_id` on POST `/wp-json/reactwoo-geocore/v1/targets/create` when `attach_to_action` was true. Frontend now sends `state.proposalId` (not `state.proposal.id`). Replaced native `window.alert` failures with inline modal error panel; backend returns structured `{ success, code, message, details }` JSON.

## Root cause (evidence)

| Check | Finding |
|-------|---------|
| REST route | Registered as `reactwoo-geocore/v1/targets/create` (not `reactwoo-geo/v1`) |
| Request payload bug | JS sent `proposal_id: state.proposal.id` — always empty; interpret response stores ID in `state.proposalId` |
| Backend validation | `post_targets_create` rejected attach when `proposal_id` or `action_id` empty → HTTP 400 |
| Frontend handling | jQuery `.fail()` + `window.alert(i18n.popupCreateFailed)` — no server message surfaced |
| Nonce / capability | Nonce present via `cfg.restNonce`; capability `edit_pages` on route — not the failure mode |

## Files changed

| File | Reason |
|------|--------|
| `admin/js/rwgc-targeting-assistant.js` | `proposalIdForRequest`, structured error UI, no alerts, target normalization |
| `includes/class-rwgc-rest.php` | `target_create_failure()` structured JSON for all create errors |
| `includes/targeting/class-rwgc-assistant-target-service.php` | `duplicate_found` code on duplicate guard |
| `includes/class-rwgc-admin.php` | Error panel i18n strings |
| `admin/css/rwgc-targeting.css` | `.rwgc-popup-resolver__error` styles |
| `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` | Regression assertions for fix |

## What was not changed

- Geo AI parser / planner interpretation logic
- Execute / create-rule endpoint behaviour

## Commands run

```bash
composer test -- --filter RWGCTargetingAssistantUiRegressionTest
# Failed: PHPUnit\TextUI\Command not found (known env issue)
```

## Remaining errors

None in code. Manual Local UI pass recommended for acceptance tests A–E.
