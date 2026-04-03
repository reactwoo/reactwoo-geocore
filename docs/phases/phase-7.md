# Phase 7 — Geo Commerce (Woo) (in progress in satellite)

Aligned with `docs/geo-core-cursor-master-plan.md` §10 Phase 7 and §4 rules 13–14.

**Product boundary:** **Geo Commerce** is a **separate WordPress plugin**. It implements WooCommerce overlays, pricing rules, and (later) attribution. It **requires Geo Core** for visitor geo and must **not** ship a parallel MaxMind stack. **`reactwoo-geo-commerce/`** (hook `rwgcm_loaded`).

## Goal

Commerce-specific personalization on top of the shared engine.

## Geo Core’s role

- Stable **`rwgc_*` / `RWGC_*`** APIs and filters.
- No Woo-only business logic in Geo Core beyond generic context/rules foundations.
- **Discovery:** `rwgc_is_woocommerce_active()` (filter `rwgc_is_woocommerce_active`); REST **`GET …/capabilities`** includes **`woocommerce_active`** (bool) when REST is enabled — no visitor PII.

## Checklist (Geo Commerce plugin authors)

1. Declare **Geo Core** as a required dependency (active check: **`rwgc_is_geo_core_active()`** or `class_exists( 'RWGC_Plugin' )`).
2. Use **`rwgc_is_woocommerce_active()`** (or REST **`woocommerce_active`**) before running Woo-only code paths.
3. Read visitor geo via **`rwgc_get_visitor_data()`** / **`RWGC_API::get_visitor_data()`** — filter visitor payload with **`rwgc_geo_data`** only when extending, do **not** reimplement MaxMind.
4. Subscribe to **`rwgc_geo_event`** / **`rwgc_route_variant_resolved`** for analytics if needed; discovery: **`GET …/v1/capabilities`** (`plugin_slug`, `text_domain`, `integration`, …).
5. Prefix your own hooks with a distinct slug (e.g. `rwgc_commerce_*`) to avoid collisions with Core.

## Shipped in Geo Commerce plugin

- **`rwgc_geo_data`**: `rwgc_commerce_cart_items` when cart available; filter **`rwgcm_geo_data`**.
- **Pricing:** **Geo Core → Commerce pricing** — country + optional categories; storefront parity via **`rwgcm_apply_catalog_price`** / **`RWGCM_Catalog_Price`**; **variable** products via **`RWGCM_Catalog_Price_Variable`** (variation price filters + per-country cache hash). Bundle / composite component lines skipped by default (`bundled_by`, `composite_parent` / `composite_item`); **`rwgcm_skip_pricing_for_cart_item`**.
- **Orders:** checkout capture **`_rwgcm_visitor_country_iso2`** + time; **`rwgcm_order_attributed`**; filter **`rwgcm_order_visitor_geo`**; note on order admin; **orders list** column **Visitor country** (HPOS + legacy), **sortable** by that meta.
- **Attribution:** UTM + **`gclid`** / **`fbclid`** from query string → first-touch / last-touch cookies → **`rwgcm_checkout_order_meta`** (`_rwgcm_*_ft` / `_rwgcm_*_lt`); dashboard toggle; filters **`rwgcm_store_utm_on_orders`**, **`rwgcm_attribution_query_keys`**.
- **Dashboard:** pricing + **fee rules** status; links to **Commerce fees** (country-based cart fees via **`rwgcm_cart_fees`**, optional fee **tax class** when taxable, filter **`rwgcm_fee_rule_rows`**); **REST discovery** (capabilities JSON) when Core REST is on.
- **Shipping:** **`rwgcm_package_rates`** — wraps Woo **`woocommerce_package_rates`** (priority 99); third argument is visitor **ISO2** from Geo Core.
- **Coupons:** **Usage restriction** tab — **Allowed countries (Geo Commerce)** (Woo country multiselect); meta **`_rwgcm_allowed_countries`**; filters **`rwgcm_coupon_allowed_for_visitor`**, **`rwgcm_coupon_valid_when_country_unknown`**.

## Next

- Edge cases for third-party bundle/composite plugins — **`rwgcm_skip_pricing_for_cart_item`** and related hooks (master plan **§18**).
