# Decisions

> Architecture and workflow choices that should survive beyond one chat thread.

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-06-26 | Popup target resolution uses multi-view modal in Geo Core JS + `reactwoo-geocore/v1/targets/*` REST | Keeps Elementor popup create/search server-side; avoids listing all popups as top-level resolver buttons |
| 2026-06-26 | `ai-handoff/current-task.md` archived when task ships; `cursor-output.md` updated each pass | Planner reads output files, not full chat |

## ReactWoo defaults

- **ChatGPT/Codex:** diagnose, spec, acceptance criteria, review patches.
- **Cursor:** apply patches, local edits, run smallest validation, write `cursor-output.md`.
- **Repo markdown:** shared memory — not chat history.
- **No duplicate fallbacks:** fix root cause; do not stack defensive workarounds.
