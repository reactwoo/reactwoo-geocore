# Current task

> **Completed** in Geo Core **v1.8.95** (2026-06-29). Planner may replace this file for the next task.

## Problem

Google Ads OR URL resolver conflated Google Ads mapping with the already-valid URL child. Standard UTM was not reliably preselected; custom mapping appeared recommended.

## Expected

1. Standard Google Ads UTM tracking selected by default; Apply enabled on open.
2. Custom mapping advanced-only, below standard options.
3. Valid URL sibling shown read-only with examples; optional Edit URL match flow.
4. OR group preserved after Google Ads mapping apply.

## Acceptance test

- [x] Standard preselected; custom not default; Apply enabled.
- [x] Also-valid URL section + Edit URL match sub-editor.
- [x] Structural PHPUnit guards.
- [ ] Manual browser pass on Local (planner/human).

## Do not touch

- Geo AI parser / planner interpretation logic.
- Popup target resolver create/select flow.

## Cursor instructions

Task shipped — see `cursor-output.md`. Next task: replace this file.
