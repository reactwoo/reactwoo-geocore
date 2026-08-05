# Cursor output

## Status

**done** — Atomic Flexbox frontend geo hide fix for Geo Core **v1.8.127**.

## Files changed

- `includes/integrations/elementor/class-rwgc-elementor-frontend.php` — register `e-flexbox` / `e-div-block` / `e-grid` (and tabs) `should_render` immediately; stop waiting on JS-only `elementor/frontend/init`; discover extras via `elementor/elements/elements_registered`
- `reactwoo-geocore.php`, `readme.txt` — **1.8.127**

## Root cause

Atomic nestable hooks were attached on `elementor/frontend/init`, which exists only in Elementor’s **JS** frontend and never fires in PHP. US-only Flexbox therefore never received `should_render`, and Twig containers ignore classic wrapper CSS hides.

## What was not changed

- Evaluator / empty-country product rule
- Atomic chips control / schema union (1.8.126)

## Remaining (manual)

- Publish Home (Variant), hard-refresh as UK — “UNITED STATES” Flexbox must not render
