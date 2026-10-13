# Current task

Geo Core **1.9.1** is the tagged release on `main` (annotated tag `v1.9.1`, 2026-10-10). Same-visit returning-visitor classification shipped in **1.8.165** and is no longer the active task.

## Done this pass

Notable releases since 1.8.165. Dates are annotated-tag dates. The changelog dates 1.8.168 as 2026-10-06 and 1.9.1 as 2026-10-09.

- **1.8.168** (2026-10-07): five fixes. A deleted or missing rule never matches: "show only if" content stays hidden, "hide if" content stays visible, variants fall back to the default.
- **1.8.169:** the QUIC.cloud visitor IP fix behind a local reverse proxy.
- **1.8.170** and **1.8.171:** the editor warning for blocks and elements that reference a deleted or unpublished rule. 1.8.171 made it actually attach in Elementor 4.x, kept stale ids selected, and limited the picker to published rules.
- **1.9.0:** the WordPress.org build target (`scripts/package_zip.py --target wporg`, slug `reactwoo-geo-core`, separate Plugin URI `https://reactwoo.com/geo-core/`), private-by-default telemetry, and the MaxMind notice fix. The WordPress.org text domain remains `reactwoo-geocore`.
- **1.9.1:** Geo Content block fixes. The rule-controls config key, InnerBlocks with backward compatibility, the rule builder mounting when selected, and a published-only library picker.

## In progress (1.9.2)

- PR #80 — telemetry value `0` is read as opted in
- PR #79 — empty Geo Content country list on WordPress 6.2–6.4

## Remaining (Gate E)

Next architecture gate is **Gate E**. Operator steps, in order:

1. Cloud Commerce product IDs (`rwcc_settings`, if still empty)
2. Sign in at `https://decision.reactwoo.com` (private window)
3. Paid checkout E2E
4. API Manager 2.1.13 on ReactWoo.com if production is still on 2.1.12
5. Live attribution

Backlog is in `known-issues.md`: Elementor `_elementor_element_cache`, `infer_provenance_from_rule_set()` ignoring `rules[]`, the PHPUnit baseline, security/leak drafts (#63 blocks Gate E), and the 63 older `cursor/*` drafts (#1–#67).

## Release process

Releases ship from reviewed `cursor/*` pull requests after real-browser QA on the staging site. `main` is fast-forwarded or merged. An annotated `v*` tag triggers `publish-update.yml` (ReactWoo channel: R2 plus `api.reactwoo.com`). WordPress.org SVN is a separate upload. Tags need the owner's explicit approval.

## Do not

- Re-run `bind_production_cloud_catalogue.sql` against Local
- Restore HTTP `POST /api/v1/deploy`
- Unhook Elementor add-ons
- Tag or publish without the owner's explicit approval
