# AI handoff workflow — Geo Core family

File-based bridge between **ChatGPT/Codex** (planner) and **Cursor** (editor) — no API integration.

Applies to: **reactwoo-geocore**, **reactwoo-geocore-pro**, **reactwoo-geo-ai**, **reactwoo-geo-optimise**, **reactwoo-geo-commerce**.

## Files (`ai-handoff/` in each repo)

| File | Owner | Purpose |
|------|-------|---------|
| `current-task.md` | Planner | Problem, expected, acceptance test, do-not-touch |
| `cursor-output.md` | Cursor | Files changed, commands, remaining errors |
| `test-output.md` | Human | Failing command + last ~80 lines of output |
| `known-issues.md` | Both | Debug journal — do not repeat failed fixes |
| `decisions.md` | Planner | Suite architecture choices |

## Loop

1. Planner fills `current-task.md` (or export from **ReactWoo Flow** → unzip into target repo).
2. Cursor: *Read `ai-handoff/current-task.md` and `known-issues.md`. Implement. Update `cursor-output.md`.*
3. You run tests → paste into `test-output.md`.
4. Planner receives only `cursor-output.md` + `test-output.md` + your question.

## Paste-back to ChatGPT/Codex

```markdown
## Cursor Output
[paste ai-handoff/cursor-output.md]

## Test Output
[paste ai-handoff/test-output.md]

## Current Question
What should be done next?
```

## Geo family rules (also in `decisions.md`)

- **Geo Core** owns visitor geo, routing contracts, REST `/capabilities`, `RWGC_Rule_Evaluator`.
- **Satellites** require Core — do not duplicate MaxMind/detection or fork the evaluator.
- **GeoCore Pro** extends portable targeting via Core hooks only.
- **No CSV country lists** in admin UIs — use prepopulated selects (master plan §5.1).
- City-based **page routing** belongs in Geo Elementor, not Core `RWGC_Routing`.

## Cursor rule

`.cursor/rules/ai-handoff.mdc` — always read task + known-issues; write `cursor-output.md` after each pass.

## Bootstrap / refresh

From **reactwoo-flow** repo:

```bash
python scripts/init-ai-handoff.py --family geo --force
```

Full workflow origin: `reactwoo-flow/docs/ai-handoff-workflow.md`.
