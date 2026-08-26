# Cursor output

## Status
done

## Task
Critical bug hunt (cron). Gutenberg Experience Slot `save_post` rewrite passed unslashed `serialize_blocks()` output into `wp_update_post()`, which `wp_unslash()`s and strips `\"` from sibling block JSON.

## Files changed
- `includes/integrations/gutenberg/class-rwgc-gutenberg-experience-slot.php` — `wp_slash()` the rewritten `post_content`
- `tests/test-rwgc-gutenberg-experience-slot.php` — lock in that quoted image-alt JSON survives implied `wp_unslash`

## What was not changed
- Plugin version (1.8.163)
- Elementor slot save (already `wp_slash`s `_elementor_data`)
- Open PRs #1–#55 (not duplicates of this path)

## Commands run
- `php tests/test-rwgc-gutenberg-experience-slot.php` — all passed

## Remaining
None for this bug.
