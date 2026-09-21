# Current task

Same-visit returning-visitor classification is implemented in Geo Core **1.8.165**.

## Done this pass

- First browsing session stays new across page views and AJAX (`rwgc_rv`)
- Persistent `rwgc_returning` still marks the next session as returning
- Geo Core tests required check no longer looks failed on `cursor/*` WIP PRs

## Remaining (Cloud commerce, unchanged)

1. Bind `rwcc_settings` product IDs if still empty
2. Private-window Sign in at `https://decision.reactwoo.com`
3. Paid production checkout E2E
4. Gate E live attribution

## Do not

- Re-run `bind_production_cloud_catalogue.sql` against Local
- Restore HTTP `POST /api/v1/deploy`
- Unhook Elementor add-ons
- Merge Cursor Cloud `cursor/*` PRs; ship tagged releases on main
