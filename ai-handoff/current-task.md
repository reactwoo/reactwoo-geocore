# Current task

**Production Cloud commerce is enabled** (operator SQL + `REACTWOO_CLOUD_BRIDGE_ENABLED`, 2026-08-21).

## Verified on production

- Store: parent **3166** purchasable; variations **3172–3177** at PLAN GBP; `rwcc-cloud-product-copy` on the product page
- License: package slug `reactwoo-decision-cloud` id **2271**, `is_active=1`
- Decision Cloud: `GET /health` → `0.17.9`; `store_login_url` includes `rwcc_open_cloud=1`

## Remaining

1. If `rwcc_settings` product IDs are still empty, paste them in wp-admin **or** `wp eval-file scripts/merge_production_cloud_settings.php` (empty keys only; never overwrites secrets). Individuals: 2294 / 2893 / 2891.
2. Deploy API Manager **2.1.13** (ISO start-date conversion `132e7fe`) to ReactWoo.com if production is still on 2.1.12 — needed for pending-individual materialize.
3. Private-window Sign in at `https://decision.reactwoo.com` (identity cutover).
4. Paid production checkout E2E (operator).
5. Gate E live attribution.

## Do not

- Re-run `bind_production_cloud_catalogue.sql` against Local
- Restore HTTP `POST /api/v1/deploy`
- Unhook Elementor add-ons
