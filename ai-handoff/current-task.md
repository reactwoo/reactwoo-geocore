# Current task

Returning / new visitor conditions are implemented in Geo Core **1.8.164**.

## Done this pass

- First visit is never returning, even with UTM on the request
- Persist `rwgc_returning` for the next visit; prior `rwgc_ft` still counts
- Portable types `returning_visitor` / `new_visitor` evaluate in Core
- Capability `visitor.returning` for Cloud manifests
- Decision Cloud **0.17.10** portal schema includes Returning visitor

## Remaining (Cloud commerce, unchanged)

1. Bind `rwcc_settings` product IDs if still empty
2. Private-window Sign in at `https://decision.reactwoo.com`
3. Paid production checkout E2E
4. Gate E live attribution

## Do not

- Re-run `bind_production_cloud_catalogue.sql` against Local
- Restore HTTP `POST /api/v1/deploy`
- Unhook Elementor add-ons
