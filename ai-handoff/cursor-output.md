# Cursor output

## Status
done

## Task
Critical-bug hunt (cron). Product geo targeting leaked on WooCommerce Blocks / Store API listings.

## Files changed
- `includes/integrations/woocommerce/class-rwgc-product-visibility.php` — exclude geo-hidden products from frontend/Store API listing queries (classic `is_visible` is not enough)
- `tests/test-rwgc-product-visibility.php` — country hide/show, variation inherit, Store API collection vs single, query `post__not_in`

## What was not changed
- No `woocommerce_is_purchasable` / add-to-cart block (classic product URL still works)
- No GeoIP / REST document-geo / entitlement / Cloud pairing changes
- Plugin version left at 1.8.163

## Commands run
- `php tests/test-rwgc-product-visibility.php` — ALL PASSED

## Remaining
- Direct product URLs and Store API single-product GET still return geo-hidden SKUs (same as classic `is_visible`)
- Purchase eligibility remains Geo Commerce `rwgcm-availability` unless product copy is re-scoped
