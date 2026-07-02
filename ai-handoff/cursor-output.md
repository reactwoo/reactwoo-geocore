# Cursor output — rules page styling enqueue fix

## Status

**done** — ship **v1.8.107** and re-check staging.

## Task

Verify/fix staging Rules page + Rule Tester still looking like plain WP admin.

## Files changed

| File | Why |
|------|-----|
| `includes/class-rwgc-visibility-rule-tester-assets.php` | Robust screen detection, priority 25, `ensure_suite_styles()`, shared modal shell check |
| `admin/views/visibility-rule-tester-modal.php` | Empty dialog shell (JS mounts header/body/footer) |
| `admin/js/rwgc-visibility-rule-tester.js` | Render into dialog; scrollable body section |
| `admin/css/rwgc-rule-tester.css` | Flex form layout, sticky header/footer |
| `admin/css/rwgc-rules-page.css` | `.rwgc-btn` fallback styles scoped to rules page |
| `reactwoo-geocore.php` / `readme.txt` | **v1.8.107** |

## What was not changed

Evaluator, storage, parser, resolvers.

## Staging checks

See `ai-handoff/current-task.md` checklist.

## Release

Commit + tag `v1.8.107` + push + `npm run package:zip` when ready.
