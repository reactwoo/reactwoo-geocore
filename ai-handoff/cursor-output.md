# Cursor output - Critical bug investigation

## Status

**needs-review** - fix implemented; validation pending local PHP tooling.

## Bug fixed

Unresolved configured visibility rules were treated as a successful portable match in `RWGC_Targeting_Surface_Evaluator::evaluate()`.

Impact:
- `show_if` surfaces with a deleted/corrupt saved rule could render for every visitor.
- `hide_if` surfaces with a deleted/corrupt saved rule could be hidden for every visitor.

Trigger:
1. Apply a saved visibility rule to an Elementor surface.
2. Save the surface in `show_if` or `hide_if` mode.
3. Delete, trash, or corrupt the saved rule so the library ID no longer resolves.
4. Load a page containing that surface.

## Files changed

- `includes/targeting/class-rwgc-targeting-surface-evaluator.php`
  - Treat enabled visibility-rules layers with no resolvable rule set as `portable_match = false`.
  - Preserve `visibility_rules_empty` as the evaluation reason.
  - Include unresolved visibility rules in `rules_match`.
- `tests/Targeting/RWGCTargetingSurfaceEvaluatorTest.php`
  - Added regression coverage for stale library IDs in both `show_if` and `hide_if`.

## What was not changed

- No rule registry behavior was changed.
- No Elementor editor UI behavior was changed.
- No preview/tester medium-severity UX issues were changed.

## Commands run and results

- `git status --short --branch` - clean at start on `cursor/critical-bug-investigation-29c6`.
- `git log --oneline --decorate -n 20` - inspected recent release commits.
- `git show --stat --summary ...` - inspected v1.8.106 through v1.8.112 change areas.
- `vendor/bin/phpunit -c phpunit.xml.dist --stderr` - failed before validation because `vendor/bin/phpunit` is not installed.
- `php -v && composer --version` - failed because PHP is not installed in the container.

## Remaining errors

- Local PHP/PHPUnit validation is pending until PHP and Composer dependencies are installed.

## Next validation

```bash
vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Targeting/RWGCTargetingSurfaceEvaluatorTest.php
```
