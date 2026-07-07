# Releases, git tags, and staging builds

Use the same workflow for **ReactWoo Geo Core**, **Geo Elementor**, and the **Geo satellite** plugins (**reactwoo-geo-ai**, **reactwoo-geo-optimise**, **reactwoo-geo-commerce**): bump the plugin version, commit, tag, push, then deploy or copy the build to staging.

## Canonical ReactWoo Geo Core slug (all plugins)

Use the **same** string everywhere WordPress or REST refers to the plugin directory / dependency:

| Use | Value |
|-----|--------|
| `wp-content/plugins/` folder & zip root | **`reactwoo-geocore`** |
| Plugin header **`Requires Plugins:`** (Geo Elementor + satellites) | **`reactwoo-geocore`** |
| PHP **`RWGC_PLUGIN_SLUG`** (Geo Core) | **`reactwoo-geocore`** |
| REST namespace **`register_rest_route`** | **`reactwoo-geocore/v1`** |
| License **`packages.slug`** (satellites) | matches each product (`reactwoo-geo-ai`, …) — **Geo Core** row uses **`reactwoo-geocore`** where applicable |

Do **not** use `reactwoo-geo-core` (extra hyphen). Dependent repos’ **`package.json`** includes **`reactwooBuild.geoCoreDependencySlug`: `"reactwoo-geocore"`** so release/build tooling can assert parity with the PHP header.

## Build manifest (`package.json` → `reactwooBuild`)

Geo Core and the three Geo satellite repos include **`package.json`** with:

- **`reactwooBuild.pluginFolder`** — first directory inside the release zip; after upload, WordPress installs to `wp-content/plugins/{pluginFolder}/`. This must stay aligned with **`Requires Plugins:`** dependency slugs (e.g. `reactwoo-geocore`) and PHP constants such as **`RWGC_PLUGIN_SLUG`**.
- **`reactwooBuild.zipFile`** — base filename of the zip produced next to the repo root (for CI, R2, or manual distribution). **Local** `npm run package:zip` appends the plugin header version (e.g. `reactwoo-geocore-1.8.33.zip`). **CI** (`CI=true`) keeps the unversioned name so publish workflows stay unchanged.
- **`reactwooBuild.versionInZipFile`** — optional; default `true`. Set `false` to always emit the unversioned `zipFile` name.
- **`reactwooBuild.mainPhp`** — optional; main plugin PHP file used to read `* Version:` (defaults to `{pluginFolder}.php`).
- **`reactwooBuild.geoCoreDependencySlug`** (satellites + Geo Elementor) — always **`reactwoo-geocore`**; must match **`Requires Plugins:`** and Core’s **`pluginFolder`**.
- **`reactwooBuild.pluginSlug`** (Geo Core only) — same as **`pluginFolder`** for the Core zip.

Running **`npm run package:zip`** executes **`scripts/package_zip.py`**, which reads those keys. If `reactwooBuild` is missing or invalid JSON is skipped, the script falls back to the historical default folder name for that repo.

**Changing the folder/slug** (e.g. renaming the plugin directory in the zip) creates a **new** plugin identity in WordPress: the site may show **two** plugins until the old one is **deactivated and deleted**. Do not rely on “update in place” across different `pluginFolder` values. Coordinate a **version bump**, **annotated git tag**, fresh **`package:zip`**, and license **`packages.slug`** / API product keys if those reference the old slug.

## 1. Version bump (before tag)

1. Set **`Version:`** in the main plugin PHP file (e.g. `reactwoo-geocore.php`, `reactwoo-geo-ai.php`) to the new semver (project style often uses **four segments**, e.g. `0.1.12.0`).
2. Match the same value in any **`define( 'RWGC_VERSION', ... )`** (or `RWGA_VERSION` / `RWGO_VERSION` / `RWGCM_VERSION`) if present.
3. Update **`readme.txt`** **`Stable tag:`** and add a **Changelog** entry for the release.
4. Commit with a clear message, e.g. `Release 0.1.12.0 — dashboard REST smoke test`.

## 2. Tag and push

From the plugin repo root:

```bash
git status
git add -A
git commit -m "Release VERSION — short summary"
git tag -a "vVERSION" -m "Release VERSION"
git push origin main "vVERSION"
git ls-remote --tags origin "vVERSION"
```

Replace `VERSION` with the exact version string (e.g. `1.8.20`). The annotated tag `v1.8.20` should match the plugin header so support and CI can correlate git ↔ installed zip.

**A release is incomplete until the tag appears on GitHub** (`git ls-remote`). Local commit + tag alone do not publish to R2.

### Avoid slow or flaky releases (agents + Windows)

**Do not** chain everything in one line (`commit && tag && archive && push main && push tag`). On Windows Git Bash, `git tag -a` and the **second** `git push` have been observed to **segfault** (exit `139`) or fail with fork errors; agents then retry and the terminal appears to hang.

**Do not** run two separate pushes (`git push origin main` then `git push origin vVERSION`) unless the combined push failed. One combined push updates `main` and publishes the tag.

- **Push tag `v*`** → triggers **Publish ReactWoo Geo Core Update** (R2 + API) — this is the release that matters.
- **Geo Core tests** runs on **pull requests** to `main` only (not on tag pushes). Cursor Cloud WIP PRs on `cursor/*` branches are skipped in CI; agents should **not** push those branches — ship tagged releases on `main` only (see `.cursor/rules/cursor-git-tagged-releases-only.mdc`).

**Recommended agent sequence (Geo Core):**

```bash
# 1) Commit (separate command; use -c core.hooksPath=/dev/null only if hooks hang)
git add … && git commit -m "…"

# 2) Tag alone (separate command on Windows)
git tag -a "vVERSION" -m "ReactWoo Geo Core vVERSION"

# 3) One push for branch + tag (not two pushes)
git push origin main "vVERSION"
```

**Local zip:** Optional. This repo’s **`publish-update.yml`** builds the distribution zip on **tag push** (`python scripts/package_zip.py`). Skip local `git archive` during agent releases unless you need a file before CI finishes.

**CI:** Tests run on **pull requests** to `main`, not on every push to `main`, so release pushes do not spawn a redundant (and sometimes failing) test run before the tag publish workflow.

## 2b. R2 + updates API publish (Geo suite — all product repos)

This is the **plugin release pipeline** for **Geo Core**, **GeoCore Pro**, **Geo AI**, **Geo Optimise**, **Geo Commerce**, **Geo Elementor**, and other ReactWoo products that ship zips via Cloudflare R2. It is **not** the same as deploying **`reactwoo-api`** or **`react-license`** on the cPanel host.

**On tag `v*` (each plugin repo’s `publish-update.yml`):**

1. Build zip (`npm run package:zip` / `scripts/package_zip.py`).
2. Upload to R2: `aws s3 cp … s3://{R2_BUCKET}/plugins/{slug}/{version}/{slug}.zip`.
3. Register metadata: `POST https://api.reactwoo.com/api/v5/updates/publish` with **`Authorization: Bearer <UPDATES_PUBLISH_TOKEN>`**.

**No `git` runs on the server for this path.** GitHub Actions does not SSH to cPanel and does not `git pull` on `api.reactwoo.com`. Orbi’s “password authentication is not supported for Git operations” message in **`api.reactwoo.com/logs/err.log`** refers only to the **API self-deploy webhook** (`POST /api/v5/deploy` → `git fetch`), not to Geo plugin R2 publishes.

| GitHub org/repo secret | Role |
|------------------------|------|
| **`R2_ACCESS_KEY_ID`**, **`R2_SECRET_ACCESS_KEY`**, **`R2_ENDPOINT`**, **`R2_BUCKET`** | Upload plugin zip to Cloudflare R2 |
| **`UPDATES_PUBLISH_TOKEN`** | Bearer token for **`/api/v5/updates/publish`** — must match **`UPDATES_PUBLISH_TOKEN`** in **`api.reactwoo.com` `.env`** |

**Not used for R2 publish:** WordPress application passwords, GitHub account passwords, **`GITHUB_DEPLOY_TOKEN`** (that PAT is only for **`reactwoo-api`** server-side `git fetch` on deploy webhook).

**Product slugs** (examples): `reactwoo-geocore`, `reactwoo-geocore-pro`, `reactwoo-geo-ai`, `reactwoo-geo-optimise`, `reactwoo-geo-commerce`, `geo-elementor`. Each repo sets **`PLUGIN_SLUG`** in its workflow.

**Customer sites** later call **`POST /api/v5/updates/check`** with a **license JWT** (paid products) or without (free slugs in **`UPDATES_FREE_SLUGS`**, e.g. Geo Core + Flow). That is separate from CI publish auth.

Details: **`reactwoo-api/docs/PRODUCTION-SERVER.md`** §6 (plugin updates).


- **GitHub:** After `git push`, staging can **`git fetch --tags`** and **`git checkout vVERSION`** (or merge `main`) in the clone used to zip the plugin for LocalWP or upload to R2.
- **Zip manually:** Checkout the tag, zip the plugin folder (contents at zip root as WordPress expects), install on staging.
- **License server:** New sellable products are **`packages`** rows (not a separate `license_types` table on production). Run **`react-license/migrations/add_geo_satellite_packages.sql`** on the license DB (uses **`is_active`**; if your DB has **`status`** instead, use **`add_geo_satellite_packages_status.sql`**). Then create **licenses** with `package_id` pointing at the new package. Activation **`productType`** must match **`packages.package_type`** (see `app.js` `/activate`).

## 4. Packages (Geo satellites)

| `packages.slug` | `package_type` (activation `productType`) | Product |
|-----------------|-------------------------------------------|---------|
| `reactwoo-geo-ai` | `reactwoo-geo-ai` | ReactWoo Geo AI |
| `reactwoo-geo-optimise` | `reactwoo-geo-optimise` | ReactWoo Geo Optimise |
| `reactwoo-geo-commerce` | `reactwoo-geo-commerce` | ReactWoo Geo Commerce |

## 5. Related repos

- **License server:** `react-license` (Node) — database migrations only on the license server DB.
- **ReactWoo API:** separate repo; plugin update payloads use **product slug** strings aligned with plugin folder names where applicable (e.g. `geo-elementor`, `reactwoo-whmcs-bridge`).
