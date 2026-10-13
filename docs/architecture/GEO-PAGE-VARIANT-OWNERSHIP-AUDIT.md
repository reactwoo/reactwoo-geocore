# Geo page-variant ownership audit

**Status:** analysis only. No product code moved.  
**Date:** 12 October 2026  
**Plan:** `reactwoo-geocore-pro/docs/REACTWOO_GEO_CORE_PRODUCT_SPLIT_CURSOR_PLAN.md`  
**Code inspected:** Geo Core `origin/main` `e976379` (plugin **1.9.1**, 9 Oct 2026). Geo Core Pro `6ac96886` (**0.1.52**). Decision Cloud and ReactWoo API coverage maps. Geo Optimise route-event subscriber.

This working tree is **26 commits behind** `origin/main` (`998f825`, plugin header **1.8.167**) and has unrelated `vendor/composer` edits. Implementation branches must start from `origin/main`, not this checkout.

## Decision

| Kind of “variant” | Owner after the split | Action |
|---|---|---|
| Full-page geo personalisation (create/link pages, master→country map, `/_gc/{version}` URLs, redirects, fallback) | **Geo Core Pro** | Move the implementation. No quota. Do not leave a locked copy in the WordPress.org zip. |
| Experience-slot / manifest variants (`includes/variants/`, `includes/slots/`, `includes/contracts/`) | **Geo Core** | Keep. Decision Cloud compiles; Core executes locally. |
| A/B experiment variants | **Geo Optimise** | Keep. Must keep working with Core and without Pro. |

## Live data that must survive

Stored on WordPress **pages** (post meta). Do not delete on upgrade, downgrade, or uninstall of Pro.

| Meta key | Constant | Meaning |
|---|---|---|
| `_rwgc_route_enabled` | `RWGC_Routing::META_ENABLED` | Routing on for this page |
| `_rwgc_route_default_page_id` | `META_DEFAULT_PAGE_ID` | Fallback page |
| `_rwgc_route_country_iso2` | `META_COUNTRY_ISO2` | One ISO2 on the legacy row |
| `_rwgc_route_country_page_id` | `META_COUNTRY_PAGE_ID` | Inline ISO2 → page map on the master |
| `_rwgc_route_role` | `META_ROLE` | `master` or `variant` |
| `_rwgc_route_master_page_id` | `META_MASTER_PAGE_ID` | Variant points at its master |

Public URLs already in the wild:

- Normal page permalinks for master and variant pages (redirect relationship, not a rewrite).
- Page-version paths: `/{page-path}/_gc/{version-name}` (`RWGC_Page_Version::ROUTE_SEGMENT`, query var `rwgc_page_version`).

Legacy licence options stay: `egp_license_key` via `RWGCP_License_Migration`. Slug aliases `geo-elementor` / `geoelementor` → `reactwoo-geocore-pro` stay in `reactwoo-api` `productSlugAliases.ts` / `cloudCoverage.ts` and Decision Cloud `src/services/plans.js`.

## Ownership table

Columns: path → current behaviour → owner now → callers → migration → test.

### Full-page geo — move to Pro

| Path | Current behaviour | Owner now | Callers | Migration | Test |
|---|---|---|---|---|---|
| `includes/class-rwgc-routing.php` | Free-tier page redirect. `maybe_route_request()` on `template_redirect`. `master_has_variant()` allows one variant per master and **disables** a second on save. Fires `rwgc_page_route_bundle`, `rwgc_route_variant_decision`, `rwgc_route_variant_resolved`. | Core | Plugin boot, admin meta box, variant manager, experience workflow, Geo Optimise page duplicator, Geo AI handoff | Move executor, quota, and authoring to Pro. Core keeps hook **names** only, with no local redirect and no quota. Pro fires the same actions. Meta keys unchanged. | Two countries on one master both resolve. No loop. Draft target is not redirected to. Unknown country uses fallback. Core-only: no redirect code in the org zip; existing meta still present. |
| `includes/class-rwgc-variant-manager.php` | `create_country_variant()` / `link_existing_variant()` refuse a second variant. Copy tells people to upgrade to GeoElementor. | Core | `RWGC_Experience_Workflow` | Move to Pro. Remove the count check. Replace the product name. | Create, duplicate, link, edit, delete with no numeric cap. |
| `includes/class-rwgc-experience-workflow.php` | Guided “create page version” writes route meta and rule provenance. | Core | Admin workflow view | Move the page-creation flow to Pro. Country visibility rules stay in Core. | Pro wizard does not publish drafts automatically. Core has no Create-variant menu. |
| `includes/class-rwgc-workflows.php` | Onboarding copy: one extra country on the free tier. | Core | Suite onboarding | Delete the free-tier page-version step from Core. Pro supplies its own entry when active. | Core getting-started never mentions a one-country cap. |
| `includes/targeting/class-rwgc-page-version.php` | Sanitises version slugs and builds `/_gc/` URLs. | Core | Routing, rule schema, rule evaluator, admin pickers | Move with the rewrite engine. | Invalid slugs rejected. Existing `/_gc/` URLs still resolve under Pro. |
| `includes/targeting/class-rwgc-page-version-routing.php` | Early rewrite, canonical guard, `noindex`, canonical link. Booted from `reactwoo-geocore.php` before plugins finish loading. | Core | Main plugin file, popups, cache compat, context resolver, rule evaluator | Move to Pro, including the early bootstrap, or existing `/_gc/` URLs 404 / get canonicalised away. | Canonical and robots unchanged vs today. No redirect loop with the country redirect engine. |
| `includes/targeting/class-rwgc-variant-rule-applications.php` | Provenance tying a visibility rule to a page version. | Core | Visibility admin, Elementor popups, surface evaluator | Move with page versions. Core rules that are not page versions stay. | Orphan detection still runs in Pro. A normal country rule in Core is unaffected. |
| `includes/engine/class-rwgc-page-route-bundle.php` | Bundle of default + ordered page variants. | Core | Legacy mapper, resolver | Move with the page router. | Priority order is stable and documented. |
| `includes/engine/class-rwgc-page-route-resolver.php` | Picks the redirect target from the bundle. | Core | `RWGC_Routing::get_route_decision_for_page()` | Move. Single redirect engine. Optimise A/B does not add a second one. | Collision: one country, one winner. |
| `includes/engine/class-rwgc-fallback-resolver.php` | First matching variant, else default. | Core | Page route resolver | Move with the page router. Not the slot renderer. | Unknown country and empty country hit the default page. |
| `includes/engine/class-rwgc-variant.php` | Page-route value object (`TYPE_CONTENT`). **Not** `includes/variants/`. | Core | Mapper, fallback resolver | Move with the page router. Do not touch the slot engine class names. | Unit tests that construct `RWGC_Variant` for routes move with Pro. |
| `includes/migration/class-rwgc-legacy-route-mapper.php` | Reads `_rwgc_route_*` into a bundle. Inline ISO2 map wins over linked children (priority 55 vs 50). | Core | `get_route_decision_for_page()` | Move. This is the reader for sites that already have one variant. | Fixture: one master + one variant meta row resolves to the same target as 1.9.1. |
| `includes/class-rwgc-plugin.php` | `require`s and `RWGC_Routing::init()` / `RWGC_Page_Version_Routing::init()`. | Core | Bootstrap | Stop booting page engines in the org build. | Activation of Core alone does not register `_gc` rewrites. |
| `reactwoo-geocore.php` | `RWGC_Page_Version_Routing::bootstrap_early()`. | Core | Load | Same early hook moves to the Pro main file. | Front request without Pro does not filter `redirect_canonical` for `_gc`. |
| `includes/class-rwgc-admin.php` (route meta box) | “Geo Variant Routing (Free)”. Save path calls `master_has_variant()`. Error: upgrade to GeoElementor. | Core | Page editor | Remove the box from Core. Pro registers it, with no cap and no GeoElementor string. | Editor has no variant panel on Core-only. |
| `includes/class-rwgc-elementor.php` (~line 311) | “Free limit: one variant per master page. Use GeoCore Pro…” | Core | Elementor document controls | Remove. Country targeting controls stay. | Elementor country show/hide still works with no Pro. |
| `admin/views/workflow-create-variant.php` | Create-variant wizard. | Core | Workflow | Move view to Pro or render only when Pro is active **and** the view ships in Pro. | Direct `admin.php` hit on Core does not create a page. |
| `admin/views/targeting-variants-page.php` | “Full-page versions” admin screen. | Core | Route registry | Move to Pro. | Screen absent from Core menus. |
| `admin/views/dashboard-page.php` | GeoElementor product row and “use GeoElementor” for multiple variants. | Core | Dashboard | Point at Geo Core Pro or remove the row. Do not offer a disabled button. | No GeoElementor string in the dashboard. |
| `readme.txt` / `README.md` | Tells directory users GeoElementor is a separate product that can use Core. README version on the plan snapshot disagreed with 1.9.1; confirm on the branch. | Core | WordPress.org | Describe Core as country visibility. Pro is a separate plugin, not a locked mode. | readme “Does this plugin limit features?” stays accurate. |

### Generic Cloud / slot — stay in Core

| Path | Current behaviour | Owner | Callers | Migration | Test |
|---|---|---|---|---|---|
| `includes/variants/*` (`RWGC_Variants`, factory, store, renderer) | Renders experience-slot variants: default, content, component, native reference. | Core | `RWGC_Plugin`, slot filter `reactwoo_experience_slot_render_variant` | **Keep.** | Slot render test still passes with Pro inactive. |
| `includes/slots/*` | Experience slots. | Core | Decision runtime | **Keep.** | Missing manifest falls back to default HTML. |
| `includes/contracts/*` | Manifest / variant / audience schema. | Core | Cloud connector, Decision runtime | **Keep.** | Schema version stays 1. |
| `includes/cloud/*` | Pairing and manifest pull. Not a visitor-time decision API. | Core | Admin, REST | **Keep.** Org zip must not phone home for updates; pairing stays opt-in. Confirm the `wporg` package already drops the updater (it does: `.distignore` excludes `includes/class-rwgc-satellite-updater.php`). | Org zip has no `class-rwgc-satellite-updater.php`. |
| `includes/class-rwgc-rest.php` | Advertises `rwgc_route_variant_decision` and `rwgc_route_variant_resolved` on `GET /capabilities`. | Core | Satellites, docs | Keep the hook names in the catalogue. Advertise capability `geo.page_variants` **only** when Pro is installed and licensed. Do not list page-variant authoring as a Core feature. | Core-only capabilities JSON has `geo.country` and no executable `geo.page_variants`. |
| `includes/platform/class-rwgc-platform-capabilities-bootstrap.php` | Registers `geo.country`, groups, and also `page.version_url`, device, time, UTM. | Core | Decision Cloud capability heartbeat | `geo.country` / `geo.country_group` stay. **`page.version_url` moves to Pro** so Core does not advertise a condition it can no longer execute. Device, time, and UTM are not the page-variant feature; leave them unless a later pass shows they are quota-locked. | Cloud publish refuses a page-variant experience when Pro is absent. |

### Geo Optimise — do not fold into Pro

| Path | Current behaviour | Owner | Migration | Test |
|---|---|---|---|---|
| `reactwoo-geo-optimise/includes/class-rwgo-events.php` | Listens to `rwgc_route_variant_resolved` for exposure. | Optimise | Keep the subscription. Pro must fire that action when it routes. Optimise A/B must not require the action. | Experiments assign and record goals with Pro **deactivated**. |
| `includes/class-rwgo-page-duplicator.php` | Copies `_rwgc_route_*` when duplicating a page. | Optimise | Keep reading the same meta constants. After the move, those constants can live in a tiny Core contract class **or** Optimise can use the literal keys. Do not invent a second meta prefix. | Duplicating a page does not fatal when Pro is absent. |
| Experiment CPT, assignment, goals | Traffic splitting for tests. | Optimise | No change in this split. Document order: Pro geo redirect runs first; Optimise assignment applies to the page that is actually served. One redirect response, not two engines fighting. | A geo redirect and an experiment on the destination do not loop. |

### Entitlements — one check, two grants

| Path | Current behaviour | Gap | Migration | Test |
|---|---|---|---|---|
| `reactwoo-geocore-pro/includes/class-rwgcp-license.php` | `is_licensed()` is true only when `rwgcp` license key option is non-empty **and** the cached license JWT is unexpired. | A Decision Cloud subscription that never writes that option leaves Pro dark, so the customer looks unpaid. | Keep using `license.reactwoo.com` and the API slug alias. Pro treats a valid Cloud-bundle grant for `reactwoo-geocore-pro` as the same entitlement as a standalone key. Do not add a second license server. | Standalone key enables routing. Bundle-only site enables routing. Expired bundle with a separate Pro key still enables routing. Expired everything disables **new** authoring; stored pages and meta remain. |
| `reactwoo-geocore-pro/includes/class-rwgcp-bootstrap.php` | `rwgc_pro_enabled` and `rwgc_advanced_targeting_enabled` follow `is_licensed()`. | Same gap. | Page-variant capability availability uses that filter. | REST/MCP does not report `geo.page_variants` when the filter is false. |
| `reactwoo-geocore-pro/includes/integrations/class-rwgcp-license-migration.php` | Copies `egp_license_key` into the Pro option when Pro’s key is empty. | None. Required for GeoElementor customers. | **Keep** until a dated sunset. | Old key still activates Pro. |
| Decision Cloud `src/services/plans.js` | Starter covers `reactwoo-geocore-pro`. Growth/Scale add Commerce and Optimise. Aliases `geo-elementor`. | Coverage is commercial truth. The site plugin must honour it. | No new SKU. | `canonicalPluginSlug('geo-elementor')` stays `reactwoo-geocore-pro`. |
| `reactwoo-api/src/config/cloudCoverage.ts` | Same SKU map. | Do not change provisioning in this work. | Document only. | — |

### GeoElementor — customer-visible vs compatibility

| Surface | Kind | Action |
|---|---|---|
| `includes/class-rwgc-upsells.php` card `geoelementor` | Purchasable-style add-on card | Remove. Do not replace it with a locked teaser. |
| `includes/class-rwgc-admin-ui.php` GeoElementor card (on `origin/main`) | Admin card | Remove. |
| `includes/class-rwgc-module-registry.php` label GeoElementor | Module label | Rename to the real integration or drop the module. |
| Dashboard, meta box, variant manager, `readme.txt` | Product name | “ReactWoo Geo Core Pro”. |
| `includes/class-rwgc-plugin.php` and `class-rwgc-geoip.php` autoload candidates `GeoElementor/vendor/autoload.php` | Runtime fallback if Core’s MaxMind vendor is missing | **Keep** as internal compatibility until Core’s own `vendor/` is proven on the org zip. Not a product name in the UI. |
| `includes/class-rwgc-migration.php` reading legacy GeoElementor MaxMind settings | Installed-site migration | **Keep.** |
| `RWGCP_License_Migration`, API slug aliases, `EGP_*` in the GeoElementor repo | Licence and stored config | **Keep.** Do not archive that repo in this pass. |
| `includes/targeting/class-rwgc-rule-registry.php` `SOURCE_LEGACY_GEO = geo_elementor_legacy` | Stored rule source id | **Keep** the string. It is data, not branding. |

## UX inventory (Core screens that advertise page variants or GeoElementor)

Remove from the WordPress.org plugin. Pro may add its own screens at runtime. No disabled row, no “upgrade to unlock” control.

| Surface | What the user sees today |
|---|---|
| Page editor meta box | “Geo Variant Routing (Free)” and a one-variant error |
| Elementor document controls | One-variant limit, “use GeoCore Pro” |
| Dashboard | GeoElementor column; “use GeoElementor” for more variants |
| Add-ons / upsells | GeoElementor card, Learn more → reactwoo.com |
| Getting started / workflows | Create a country-specific page version, free-tier wording |
| Targeting variants admin view | Full-page versions |
| Create-variant wizard | Creates a linked page |
| Usage copy | “page versions are being used” is fine if it does not offer creation |
| `readme.txt` FAQ | Names GeoElementor as an add-on |

Country detection, the Geo Content block, Elementor country visibility, shortcodes, and REST location stay, with no quota.

## WordPress.org packaging (origin/main, not this checkout)

`scripts/package_zip.py --target wporg` reads `.distignore`. Confirmed on `e976379`:

| Review item | Current `origin/main` | Still required |
|---|---|---|
| Guideline 5 trialware | One-variant guard and upgrade strings are in PHP that the zip includes | Remove the feature from the org package, not only the sentence |
| Updater | `.distignore` excludes `includes/class-rwgc-satellite-updater.php` for `wporg` only | Re-check the built zip |
| `composer.json` | Listed in `.distignore` **and** in the packager ban list (~line 237) | Review asked for the manifest **in** the zip, without secrets. Reverse that exclusion. Do not ship `.env`. |
| Directory icons | `.wordpress-org` is excluded. `assets/icon-128x128.png` and `assets/icon-256x256.png` are **not** in `.distignore` | Exclude those two files from the plugin zip; keep directory artwork under `.wordpress-org/` |
| Inline `<script>` / `<style>` | Not re-audited file-by-file in this pass | Next pass: `admin/views/visibility-rules-edit.php`, `class-rwgc-admin-platform.php`, `class-rwgc-elementor-popups.php` |
| MaxMind upload | `RWGC_Admin::handle_upload_mmdb()` uses `move_uploaded_file()` after nonce, capability, and `.mmdb` extension check | Switch to `wp_handle_upload()` / Filesystem and a non-public directory |
| PHP requirement | Core header on `origin/main` is **8.1**. Pro header is **7.4** | Raise Pro to 8.1 before a Pro release |

Plugin Check has not been run against a generated zip in this pass.

## Channel split (so existing sites do not go dark)

Two zips already exist in the packager:

- `--target wporg` — directory submission. **No** page-variant executor, **no** quota, **no** updater.
- ReactWoo channel (default target) — current customers. May keep a **temporary** reader that still honours existing `_rwgc_route_*` rows, plus an admin notice that points at Pro, until a published end date. That reader must not enforce “one variant” and must not ship inside the directory zip.

Pro, once installed and entitled, takes over routing and can store more than one country. It reads the same meta. It does not delete pages.

Sites that never install Pro keep every page and every meta row. They lose automatic redirects only on the channel that has dropped the executor, and only after the admin notice. That difference has to be written in the migration guide before the org zip is submitted.

## Boundary challenges

1. **`page.version_url` is both a URL router and a free rule condition.** Leaving the condition registered in Core after the router moves would advertise a feature Core cannot perform. Register it from Pro only.
2. **`RWGC_Variant` (engine) and `RWGC_Variants` (slots) share a word and must not be moved together.**
3. **Geo Optimise copies route meta and listens for route resolution.** Those contracts stay. Optimise’s own experiment variants are out of scope.
4. **Bundle entitlement is not the same as a stored Pro license key.** Coverage lists in Decision Cloud and the API are not, by themselves, a true `RWGCP_License::is_licensed()`. PR A has to connect them or Pro page variants stay unavailable to Cloud customers.
5. **Device, time, and UTM conditions are implemented in Core today** and are not the one-variant paywall. This split does not move them. Weather and GA4 **data** already belong to Pro; their provider classes still load from Core and should be checked in the trialware sweep so Core is not shipping a locked UI for them.
6. **This checkout cannot be the implementation base.** It is 26 commits and a version (1.8.167 vs 1.9.1) behind the tree the plan reviewed.

## Suggested PR order

1. **PR A** from `origin/main`: capability id `geo.page_variants` advertised only by an entitled Pro runtime; Pro owns storage, authoring, `/_gc/` rewrites, and redirects with no count limit; Cloud bundle satisfies the same check as a standalone key; Optimise hook names preserved.
2. **PR B:** Core org package stops booting that stack; ReactWoo-channel notice for sites that already have route meta; no meta deletion.
3. **PR C:** customer-facing GeoElementor copy and the upsell card. Leave migration keys and slug aliases.

Do not tag, push a release, submit to WordPress.org, or archive `GeoElementor` as part of these PRs.

## Not verified yet

- Plugin Check output on a built zip.
- Whether a live Decision Cloud subscription currently writes a `reactwoo-geocore-pro` license key onto the site.
- A line-by-line escaping / nonce pass (review items 2 and 3).
- GeoElementor repo `1c3f631` behaviour when Core’s Elementor integration is active (duplicate controls). That stays a PR C test, not a rename.
