# Cursor output

## Status
done

## Task
1.8.147 — stop setDefaultTab `.content` crash so hydrate can run.

## Files changed
- `assets/js/rwgc-elementor-widget-hydrate.js` — seed tabs_controls, wrap getElementData / $e.run, hydrate immediately
- Version 1.8.147

## What was not changed
- slim-early widgets-config
- PHP memory / timeout

## Commands run
- (JS-only; existing PHP tests unchanged)
