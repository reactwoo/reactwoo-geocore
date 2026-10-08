# Cursor output

## Status
done

## Task
Stop the Elementor editor status warning from rebuilding itself while the Geo section is collapsed or on an inactive tab.

## Files changed
- `assets/js/rwgc-elementor-library-bridge.js` — a matching warning signature returns before emptying and recreating the notice. Visibility is not part of that decision, because a collapsed section keeps the node in the DOM but not `:visible`.
- `tests/test-rwgc-country-hydrate.js` — locks the signature guard ahead of the rebuild and rejects a `:visible` rebuild condition.

## What was not changed
- Front-end evaluation, stored rule references, Gutenberg warning, status lookup, plugin version, CHANGELOG, and tags.

## Commands run
- `node tests/test-rwgc-country-hydrate.js`

## Remaining errors
None from this change.
