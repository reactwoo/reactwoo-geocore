# Cursor output

## Status

**done** — Geo Core **v1.8.120** publish pipeline dual-uploads R2 `latest/` + `latest.json`; reactwoo-api **0.1.54** adds `GET /api/v5/updates/latest/:slug`.

## Files changed

### reactwoo-geocore
- `.github/workflows/publish-update.yml` — after versioned zip upload, also write `plugins/reactwoo-geocore/latest/reactwoo-geocore.zip` + `latest.json` (no-cache)
- `docs/releases-and-git-tags.md` — document latest keys + API stable URL
- `reactwoo-geocore.php`, `readme.txt` — version **1.8.120**

### reactwoo-api
- `src/routes/updates.ts` — `GET /latest/:slug` (302 or `?format=json`)
- `src/services/pluginReleases.ts` — `getLatestRelease`, `latestArtifactKey`, helpers
- `docs/PRODUCTION-SERVER.md`, `CHANGELOG.md`, `package.json` → **0.1.54**

## What was not changed

- WordPress `/updates/check` flow (unchanged)
- Other satellite publish workflows (Geo Core only for dual-upload for now)
- Public R2/custom domain (still private bucket + signed URLs)

## Commands run

- `npm run build` in reactwoo-api — pass

## Remaining

- Deploy/push both repos so CI creates the first `latest/` objects and API serves the redirect.
