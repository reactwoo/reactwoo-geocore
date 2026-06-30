# Cursor output — Assistant View rule URL fix

## Status
done

## Files changed
- `includes/class-rwgc-visibility-rule-repository.php` — `get_edit_url()`, `assistant_rule_verification()`, `can_current_user_manage_rule()`
- `admin/js/rwgc-targeting-assistant.js` — reject `post.php` rule links; verification failure UI; retry create
- `includes/class-rwgc-admin.php` — i18n for verification failure panel
- `reactwoo-geocore.php`, `readme.txt` — v1.8.100

## Not changed
- Parser/planner, popup export, execute preflight for targets

## Commands run
- `node --check admin/js/rwgc-targeting-assistant.js` — OK

## Remaining
- Release tag/push when requested; install on staging with Geo AI 0.4.133
