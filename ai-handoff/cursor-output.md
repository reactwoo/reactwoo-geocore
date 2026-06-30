# Cursor output — popup target execute export (v1.8.98)

## Status

done

## Root cause

Server-matched popup targets (`card.target.status === 'matched'` with `resolved.id`) appeared resolved in Action Review but were **never exported** in `collectCardResolutions()` because:

1. `autoMatchPopupTargets()` returned early for server-matched targets without seeding `state.cardResolutions`.
2. `collectCardResolutions()` explicitly skipped `target` when status was `matched`.
3. `findFieldResolution()` only looked at `cardResolutions`, not `card.target.resolved`.

Execute therefore sent resolutions without popup ID → Geo AI 409 `unresolved_details: Popup target`.

## Files changed

- `admin/js/rwgc-targeting-assistant.js` — canonical popup target sync (seed, export, preflight, debug log)
- `includes/class-rwgc-admin.php` — i18n for state mismatch UI
- `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` — popup execute sync regression guards
- `reactwoo-geocore.php`, `readme.txt` — v1.8.98

## Not changed

- Geo AI parser/planner
- Google Ads mapping flow
- Popup create REST endpoint

## Commands run

- `node --check admin/js/rwgc-targeting-assistant.js` — pass

## Remaining

- Staging retest: full Free Delivery prompt → Apply Google Ads → Create rule
- PHPUnit not run locally (PHPUnit binary missing on agent)
