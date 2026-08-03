# Cursor output

## Status

**done** — Critical bug hunt (cron `eda296cd`, 2026-08-03): **no new critical bug**.

## Scope

- Tip: `d4ff4b1` / **v1.8.122** (`origin/main`)
- New since last hunt (`87fb935` / #48): Atomic Geo Visibility (`0f41724`), docs/version bumps only otherwise
- Open PRs still covering known criticals: #1–#48 (do not duplicate)

## Reviewed

- `RWGC_Elementor_Atomic_Geo` props-schema + controls (API matches Elementor Atomic Switch/Select/Text/Section)
- `RWGC_Elementor_Frontend::get_element_settings()` preferring `get_atomic_settings()` (Props_Resolver already unwraps; classic widgets unchanged)
- Nestable hooks for `e-div-block` / `e-flexbox` / `e-grid` / tabs (registered after `elements_registered`, before `frontend/init`)
- `RWGC_Surface_Settings::normalize_yes_flag` / `unwrap_atomic_props` / library→applied mirror

## Rejected (not new criticals)

1. Library ID active while Enable switch off — pre-existing evaluator `has_resolved_portable_config` behavior; Atomic only exposes select always-on
2. Atomic path omitting classic portable JSON keys — Atomic UI is library/country only; no migration plants those keys
3. Classic SWITCHER/`egp_countries` array shapes fatalling Atomic Props_Parser — no supported classic→Atomic settings copy

## What was not changed

- No product code fix (confidence bar not met for a PR)
- Open PR issues (#17/#18/#21/#25–#28/#31/#33/#35/#37/#40–#48) left as-is

## Commands run

- `git log` / `gh pr list` — tip + open PR inventory
- Elementor Atomic upstream cross-check (`get_atomic_settings`, `should_render`, Select options shape)

## Remaining

- None for this hunt. Expected outcome: no PR.
