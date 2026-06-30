# Cursor output — visibility rule fidelity + editor UI

## Status

**done** (needs local UI verification on Free Delivery recreate flow)

## Root AGENTS.md

**Absent** at Geo Core repo root — used `docs/AGENTS.md`.

## Phase 1 — Persisted rule fidelity

**Root cause:** `RWGA_Plan_Condition_Converter` dropped `pageTypes` and `condition_groups`, and flat `include.utm` flattened Google Ads into top-level AND rows.

**Fixed (Geo AI):**
- `includes/services/executor/class-rwga-plan-condition-converter.php` — `page_type`, `condition_group` with OR branches, skip flat UTM when groups exist
- `includes/services/executor/class-rwga-plan-executor.php` — `_rwga_assistant_source` meta includes target label/type/id
- `tests/Services/RWGAPlanConditionConverterTest.php` — Free Delivery fixture

**Fixed (Geo Core):**
- `includes/targeting/class-rwgc-targeting-rule-set-schema.php` — `page_type`, `request_uri`, `condition_group`
- `includes/targeting/class-rwgc-rule-evaluator.php` — evaluators for new types
- `includes/targeting/class-rwgc-context-resolver.php` — `page_type`, `request_uri` on snapshot

**Test:** `composer test -- --filter RWGAPlanCondition` — OK (1 test, 12 assertions).

**Note:** Rules created **before** this fix still have old flat UTM data — recreate the Free Delivery rule to verify.

## Phase 2 — Rules editor UI

- `admin/views/visibility-rules-edit.php` — two-column layout, cards, sidebar summary/status/actions
- `admin/css/rwgc-rule-editor.css` — scoped `.rwgc-rule-editor*` styles
- `includes/class-rwgc-visibility-rule-editor-presenter.php` — summary chips + validation
- `includes/class-rwgc-plugin.php` — require presenter
- `includes/class-rwgc-admin-visibility-rules.php` — pass `$editor_presenter`
- `includes/class-rwgc-targeting-rule-builder-assets.php` — enqueue editor CSS; advanced JSON sync
- `assets/js/rwgc-rule-builder.js` — `page_type`, `request_uri`, `condition_group` display blocks

## What was not changed

- Geo AI planner/parser interpretation
- Popup target resolver / Google Ads resolver UI
- MaxMind / satellite plugins

## Commands

```bash
cd reactwoo-geo-ai && composer test -- --filter RWGAPlanCondition
# Geo Core PHPUnit broken locally (PHPUnit\TextUI\Command) — see known-issues.md
```

## Remaining

- Bump versions + tag/release when requested
- Recreate Free Delivery rule on staging and confirm editor shows product pages + OR traffic group
- Geo Core PHPUnit vendor fix (pre-existing)
