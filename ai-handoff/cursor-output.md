# Cursor output

## Status
done

## Task
Production `requestWidgetsConfig` still HTTP 503 on admin-ajax.php.

## Files changed
### reactwoo-geocore 1.8.136
- Detector: `refresh_widgets_config` + unknown `elementor_ajax` → heavy
- Skip admin, Cloud, capabilities, Elementor control registration on that path

### reactwoo-whmcs-bridge 1.1.5.3
- Stubs on every `elementor_ajax` (matches the 1.1.5.1 changelog claim)

## What was not changed
- PHP memory / timeout (do not retry)
- Unlimited Elements widget trees (if 503 remains after both updates, that payload is next)

## Commands run
- `php tests/test-rwgc-elementor-ajax.php`
