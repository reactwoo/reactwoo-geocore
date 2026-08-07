# Cursor output

## Status

**done** — NEW critical: `publish-update.yml` always overwrote mutable R2 `latest/` + `latest.json` (and could mis-label versioned artifacts). Fixed on `cursor/critical-bug-investigation-10f6`.

## Bug

After `87fb935`, every successful publish (any `workflow_dispatch` channel, any `v*` tag including pre-releases, and version overrides that disagree with the packaged header) uploaded to:

- `plugins/reactwoo-geocore/latest/reactwoo-geocore.zip`
- `plugins/reactwoo-geocore/latest.json`

Those keys are the free stable download path (`GET /api/v5/updates/latest/reactwoo-geocore`). A beta dispatch or `vX.Y.Z-rc1` tag therefore replaced stable latest for all free downloaders. A `version=` override that did not match `Version:` in the checked-out zip could also overwrite an immutable `plugins/.../{version}/...zip` with different bytes.

## Fix

- Require resolved publish version == plugin header `Version:` in the packaged tree.
- Set `update_latest` only when `channel=stable` and version matches `^[0-9]+(\.[0-9]+)*$`.
- Skip the latest-pointer upload step unless `update_latest=true` (versioned R2 + API publish still run).

## Trigger

1. Actions → Publish ReactWoo Geo Core Update → channel=`beta` (or tag `v1.9.0-rc1`).
2. Before fix: `latest.zip` / `latest.json` become the beta/rc build (json may still say `channel=beta` while serving the free stable URL).
3. After fix: versioned artifact + API publish only; mutable latest pointers unchanged.

## Checked / not re-reported

- Elementor empty SWITCHER (#45), yes-overlay reclassify (#50), Suite Elementor copy (#47), inline wipe/shadow (#48)
- Gutenberg/portable sanitize wipes (#17/#18/#46), page-version query (#21), popup fallback (#26), assistant REST (#28), signed preview (#40), Insights (#41/#43), assistant execute/Change (#33/#44)
- Elementor request-scoped library cache (`c6fd7d3`): select options are id→title only; enriched rows keyed by post id — no concrete save data-loss path beyond open #35/#42 bridge wipe
- Engine Suite resolution / redirect-loop NAT fingerprint: previously rejected tradeoff; `posts_per_page=50` variant discovery not reachable via Core Suite (hard 1-variant guard)
- `package_zip.py` does not ship `ai-handoff/`; `docs/` has secret *names* only

## Validation

- YAML gates reviewed in `.github/workflows/publish-update.yml` (`update_latest`, header version equality).
- No PHPUnit path for GHA YAML; logic is shell conditions only.
