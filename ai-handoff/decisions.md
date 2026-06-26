# Decisions — ReactWoo Geo Core

> Architecture choices for this repo. See also `reactwoo-geocore/docs/geo-core-cursor-master-plan.md`.

| Date | Decision | Rationale |
|------|----------|-----------|
| — | Core owns visitor geo + routing contracts | Satellites must not fork detection |
| — | City page routing stays in Geo Elementor | Core `RWGC_Routing` is country-level |
| — | No CSV country inputs in admin UIs | Use prepopulated selects (§5.1) |
| — | File handoff for ChatGPT ↔ Cursor | `ai-handoff/` replaces long debug threads |

## AI handoff defaults

- **ChatGPT/Codex:** diagnose, spec, acceptance criteria, review `cursor-output.md`.
- **Cursor:** patch, run smallest test, write `cursor-output.md`.
- **Do not** grow satellite product UIs into Core — keep integration thin (hooks, REST, filters).
