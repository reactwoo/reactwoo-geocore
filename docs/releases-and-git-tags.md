# Releases, git tags, and staging builds

Use the same workflow for **ReactWoo Geo Core**, **Geo Elementor**, and the **Geo satellite** plugins (**reactwoo-geo-ai**, **reactwoo-geo-optimise**, **reactwoo-geo-commerce**): bump the plugin version, commit, tag, push, then deploy or copy the build to staging.

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
