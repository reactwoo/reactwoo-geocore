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
- **`reactwooBuild.zipFile`** — filename of the zip produced next to the repo root (for CI, R2, or manual distribution).
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
git push origin main
git push origin "vVERSION"
```

Replace `VERSION` with the exact version string (e.g. `0.1.12.0`). The annotated tag `v0.1.12.0` should match the plugin header so support and CI can correlate git ↔ installed zip.

## 3. Staging / R2 / LocalWP

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
