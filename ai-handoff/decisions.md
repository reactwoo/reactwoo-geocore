# Decisions

> Architecture and workflow choices that should survive beyond one chat thread.

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-06-26 | Popup target resolution uses multi-view modal in Geo Core JS + `reactwoo-geocore/v1/targets/*` REST | Keeps Elementor popup create/search server-side; avoids listing all popups as top-level resolver buttons |
| 2026-09-21 | Returning visitor is a Core cookie + evaluator, not UTM-on-this-request | First visit must stay new; Commerce/portable/Cloud all use `RWGC_Rule_Evaluator` / `visitor.returning` |
| 2026-10-06 | A deleted, missing, draft, or trashed visibility rule never matches | “Show only if” stays hidden, “hide if” stays visible, page variants fall back to the default. Do not skip the rule and render everyone. Editor warning for a deleted-rule reference is a later change |

## ReactWoo defaults

- **ChatGPT/Codex:** diagnose, spec, acceptance criteria, review patches.
- **Cursor:** apply patches, local edits, run smallest validation, write `cursor-output.md`.
- **Repo markdown:** shared memory — not chat history.
- **No duplicate fallbacks:** fix root cause; do not stack defensive workarounds.
