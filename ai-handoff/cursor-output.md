# Cursor output

## Status

**done** — Popup close fix for Geo Core **v1.8.125**.

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-popups.php` — `forceClosePopup` / `hideForcedModalDom` clear force-shown `!important` styles; close capture + `elementor/popup/hide` mark dismiss; suppress reopen during close window
- `reactwoo-geocore.php`, `readme.txt` — **1.8.125**

## Root cause

`forceShowModalDom()` set `display:flex !important` for allowed-country popups. Close called `modal.hide()` then returned early, or set `display:none` without `!important`, so the modal stayed visible.

## What was not changed

- Country targeting evaluation / blocked-list early hide
- Atomic General controls

## Remaining (manual)

- Open a geo-allowed popup in a matching country → close via X / overlay / Escape; modal must disappear and not reopen this session
