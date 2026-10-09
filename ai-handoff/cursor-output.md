# Cursor output

## Status
done

Geo Content 1.9.1 on the same draft branch: the rule builder mounts when the inspector is shown, and Choose from library lists published rules only. Version is 1.9.1 so `index.js` is not cached as 1.9.0. No tag, no merge.

## Root cause
The mount effect depended only on `visibilityOn` and returned immediately when `rbWrapRef` was null. Gutenberg renders that inspector node through a SlotFill only while the block is selected, so after a reload the effect never ran. Toggling visibility rules changed `visibilityOn` and was the only way to start it. If the interval did start, it stopped after 40 tries (about 5 seconds).

## Files changed
- `blocks/geo-content/index.js` — mount from the inspector ref and when `isSelected` changes. No poll.
- `includes/targeting/class-rwgc-rule-registry.php` — picker rows require `publish`.
- `reactwoo-geocore.php`, `readme.txt`, `CHANGELOG.md`, `blocks/geo-content/index.asset.php` — 1.9.1.
- Tests for selection-after-load and the published-only picker.

## Not changed
- Front-end rule matching. Drafts stay in `get_rules_for_builder()` for lookup; they are omitted from the picker.
- No tag, no merge.

## Commands
- `vendor/bin/phpunit -c phpunit.xml.dist` — Tests 109, Assertions 393, Errors 9 (`RWGC_ContextAttributionTest`), Failures 7 (`RWGCTargetingAssistantUiRegressionTest`).
- Every `composer test:*` script except `test` / `test:all` — exit 0.

## Remaining errors
- Baseline only: 9 ContextAttribution header errors, 7 Targeting Assistant UI failures.

## Status
done

Gutenberg Geo Content block: visibility-rule controls read either `advancedTargeting` or `advanced_targeting`, and the block can hold inner blocks. Existing self-closing blocks stay valid through a deprecated `save` that returns null. Server render shows or hides the inner HTML with the existing targeting gate. No version bump, no tag, no merge.

## Files changed
- `blocks/geo-content/index.js` — both config spellings; `InnerBlocks` in edit; `InnerBlocks.Content` in save; deprecated null save; stale-rule warning, replacement select, and Clear rule kept.
- `includes/targeting/class-rwgc-targeting-rule-set-schema.php` — editor context sends both spellings of the Pro flag.
- `includes/class-rwgc-gutenberg.php` — render docblock states that `$content` (inner HTML) is what gating shows or omits.
- `tests/Integrations/RWGCGeoContentBlockTest.php` — both keys, inner render, show_if hide, hide_if show.
- `tests/test-rwgc-geo-content-block.js` — key reader and editor contract.
- `composer.json`, `.github/workflows/test.yml` — `test:geo-content-block`.

## Not changed
- Version 1.9.0, changelog, front-end rule matching, Elementor bridge.
- No tag, no merge.

## Commands
- `vendor/bin/phpunit -c phpunit.xml.dist` — Tests 107, Assertions 389, Errors 9 (`RWGC_ContextAttributionTest`), Failures 7 (`RWGCTargetingAssistantUiRegressionTest`). New class: 5 tests, 15 assertions, all passing.
- Every `composer test:*` script except `test` / `test:all` — exit 0, including `test:geo-content-block` and `test:wporg-build`.

## Remaining errors
- Baseline only: 9 ContextAttribution header errors, 7 Targeting Assistant UI failures.

## Status
done

WordPress.org rejected the 1.9.0 zip before review because Plugin URI and Author URI were both `https://reactwoo.com/`. Plugin URI is now `https://reactwoo.com/geo-core/` (live page title "Geo Core - ReactWoo"). Author URI stays `https://reactwoo.com/`. Version stays 1.9.0. The plugin name and text domain are unchanged. Not tagged.

The upload handler assigns slug `reactwoo-geo-core` from Plugin Name. Text Domain `reactwoo-geocore` does not match that slug. That is a Plugin Check warning (`textdomain_mismatch`), not an upload rejection: `class-upload-handler.php` only stores `header_textdomain`, and `class-plugin-scan.php` fails the upload only when a result type is `ERROR`.

## Files changed
- `reactwoo-geocore.php` — Plugin URI.
- `.distignore`, `scripts/package_zip.py` — also reject `.tgz`, matching the upload handler's unexpected-file regex.
- `tests/test-rwgc-wporg-upload-precheck.py` — local copy of the applicable upload checks.
- `tests/test-rwgc-wporg-build.php`, `tests/test-rwgc-wporg-zip-guard.py`, `composer.json` — wire the new guard.
- `readme.txt`, `CHANGELOG.md` — 1.9.0 notes.

## Not changed
- Plugin Name, Text Domain, version 1.9.0, `publish-update.yml`.
- No tag, no merge.

## Commands
- `python3 tests/test-rwgc-wporg-upload-precheck.py --require-zip` — 0 failed. One WARN: `textdomain_mismatch`.
- `php tests/test-rwgc-wporg-build.php` and `python3 tests/test-rwgc-wporg-zip-guard.py` — passed.
- `python scripts/package_zip.py --target wporg` — `reactwoo-geocore-wporg.zip`, sha256 `47d885e11205d6ba44914ae827aefe3e673daec2897ef6edd28710e4074d1dd2`, 2,849,875 bytes, 357 files, 0 prohibited files.
- Plugin Check 2.1.0 on WordPress 7.1.3, `--mode=new --include-low-severity-errors --include-low-severity-warnings`: 0 errors, 375 warnings.
- Same install with `--slug=reactwoo-geo-core --categories=plugin_repo --exclude-checks=prefixing`: 0 errors, warnings `textdomain_mismatch` and `missing_composer_json_file`.
- Clean site `/tmp/wporg-clean` (WP_DEBUG): front `geo-smoke` 200 (United States / US, Geo Content “United Kingdom offer”) and Overview h1 “Overview” 200. No PHP notice, warning, deprecated, or fatal. No `debug.log`.

## Artifacts
- `/opt/cursor/artifacts/submission-v3/reactwoo-geocore.zip`
- `/opt/cursor/artifacts/submission-v3/plugin-check-1.9.0.txt`
- `/opt/cursor/artifacts/submission-v3/zip-manifest-1.9.0.txt`
- `/opt/cursor/artifacts/submission-v3/upload-precheck-1.9.0.txt`

## Status
done

WordPress.org rejected the 1.9.0 zip before review because `vendor/maxmind/web-service-common/dev-bin/release.sh` is an unexpected `.sh` file. The directory zip now drops `vendor/**/dev-bin/` (and other vendor docs/VCS paths), plus `.phar`, `.sh`, `.zip`, `.gz`, `.tar`, `.rar`, and `.7z` anywhere. `scripts/package_zip.py --target wporg` refuses to finish if one of those names is still in the archive. Version stays 1.9.0. Not tagged.

## Status
done

Merged `origin/main` (`c21d1de`, Geo Core 1.8.171) into `wporg-build`. Version stays 1.9.0 because main is still below 1.9.0. Not tagged. `publish-update.yml` still runs only on `v*` tags and `workflow_dispatch`.

## Merge
- Kept 1.9.0 header, `RWGC_VERSION`, Stable tag, directory description, Requires PHP 8.1, and `RWGC_DISTRIBUTION`.
- Kept main's 1.8.171 changelog (Elementor stale-rule warning) in `CHANGELOG.md` and `readme.txt`, under 1.9.0.
- Kept main's Elementor picker filter, unpublished/deleted labels, rule-status test, and `test:elementor-rule-status`.
- Kept the directory MaxMind notice and Geo Content editor-script fixes.
- `composer.json` has PHP `>=8.1` and both `test:wporg-build` and `test:elementor-rule-status`.

## Checks after the merge
- `php -l` on PHP files that differ from either parent: no syntax errors.
- `php tests/test-rwgc-wporg-build.php`: passed.
- `node tests/test-rwgc-elementor-rule-status.js`: passed.
- `node tests/test-rwgc-country-hydrate.js`: passed.
- PHPUnit: 102 tests, 374 assertions, 9 errors, 7 failures. Same 9 errors and 7 failures as main.
- Plugin Check 2.1.0 on the rebuilt WordPress.org zip, WordPress 7.1.3: 0 errors, 375 warnings.

Previous directory notes follow.

## Status
done

Directory bugfixes on PR #74, still untagged. `publish-update.yml` untouched.

## This pass

- MaxMind admin warning now uses `RWGC_MaxMind::admin_notice_code()`. A usable `.mmdb` with no license key does not raise the license-key warning. The global warning is only `no_database` (nothing usable and no key) or `missing_file` (key saved, file missing). A stale database that also has a key still shows the existing info notice. The automatic-update hint stays on the MaxMind screen and on the Overview setup step.
- Geo Content `editorScript` is `file:./index.js`. `blocks/geo-content/index.asset.php` keeps the handle `rwgc-geo-content-editor`. Country options are injected after WordPress enqueues that script.
- Verified on WordPress 7.1.3 with the directory zip: Overview has no admin notice, “Geo database ready” is checked, and the block editor inserts Geo Content with United Kingdom selected. Not the unsupported-block recovery UI.
- Screenshots 1–6 replaced. Screenshot 1 is Overview. Screenshot 2 is the Geo Content block in the editor.

## Checks
- `php -l` on changed PHP: no syntax errors.
- `php tests/test-rwgc-wporg-build.php`: passed.
- `RWGC_MaxMindDatabaseTest`: 5 tests, 8 assertions, OK.
- PHPUnit: 102 tests, 374 assertions, 9 errors, 7 failures. Same 9 errors and 7 failures as main (97 tests, 366 assertions). The extra tests are the new MaxMind cases.
- Plugin Check 2.1.0 on the installed WordPress.org build, WordPress 7.1.3: 0 errors, 375 warnings. Same warning groups as the previous pass.
- `python scripts/package_zip.py --target wporg` includes `file:./index.js` and the asset handle, and the admin class no longer contains “license key is not configured”.

## Files changed
- `includes/class-rwgc-maxmind.php`, `includes/class-rwgc-admin.php`, `includes/class-rwgc-onboarding.php`, `includes/class-rwgc-module-registry.php`, `admin/views/integrations-maxmind-page.php`
- `blocks/geo-content/block.json`, `blocks/geo-content/index.asset.php`, `includes/class-rwgc-gutenberg.php`
- `tests/Engine/RWGC_MaxMindDatabaseTest.php`, `tests/test-rwgc-wporg-build.php`
- `readme.txt`, `CHANGELOG.md`, `.wordpress-org/screenshot-1.png` through `screenshot-6.png`

## What was not changed
- Version stays 1.9.0. No tag, no release, no edit to `.github/workflows/publish-update.yml`.
- Icon and banner files were not redesigned.
- The ReactWoo updater class was not edited.

Previous branding notes follow. The license-key warning and unsupported Geo Content block described there are fixed by this pass.

## Merged from main (1.8.171)

## Task
Make the Elementor stale-rule warning attach on Elementor 3 and 4 after the panel exists, keep a deleted or unpublished rule selected, and list only published rules in the normal select. Includes the PR #73 signature guard.

## Files changed
- `assets/js/rwgc-elementor-library-bridge.js` — panel hooks and observer attach from `elementor:init` / `preview:loaded`, not at script load. Stale ids stay selected. Normal options are published rules only. The unchanged-signature guard from PR #73 stays.
- `includes/targeting/class-rwgc-rule-registry.php` — picker rows carry post status.
- `includes/integrations/elementor/class-rwgc-elementor-options.php` — Elementor select options omit non-published rules.
- `includes/integrations/elementor/class-rwgc-elementor-elements.php` — same filter on the fallback picker, plus unpublished/deleted option labels.
- `tests/test-rwgc-elementor-rule-status.js` — panel is created after the script loads.
- `composer.json`, `.github/workflows/test.yml` — `test:elementor-rule-status`.

## What was not changed
- Front-end evaluation, stored references (still not auto-cleared), plugin version, CHANGELOG, and tags.
- PR #73's branch was not updated. This branch starts from its head `f3f16fa`.

## Commands run
- `node tests/test-rwgc-elementor-rule-status.js` — passed.
- `node tests/test-rwgc-country-hydrate.js` — passed.
- `vendor/bin/phpunit -c phpunit.xml.dist` via `composer test` — Tests: 97, Assertions: 366, Errors: 9, Failures: 7.
- Every other `composer test:*` script — exit 0.

## Status
done

Branding follow-up on PR #74: owner-approved icon and banners, tagline, recaptured screenshots. Not tagged. publish-update.yml untouched.

## Branding pass

- Replaced `.wordpress-org/icon.svg`, `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, and `banner-1544x500.png` with the owner files unchanged.
- Readme short description (120 characters) and plugin header Description now lead with “Country-targeted content for your website.”
- Screenshots 1–6 recaptured from WordPress 7.1.3 with the directory build active. Site title is Demo Site. MaxMind’s public `GeoLite2-Country-Test.mmdb` was uploaded through the plugin upload form. Database on disk is Yes.
- The “MaxMind license key is not configured” notice still shows on every Geo Core admin screen. It checks an empty license key before the database file, so the test database does not clear it. Screenshot 1 is the page editor (shortcode and Geo Variant Routing), which does not show that notice.
- The country line on the MaxMind screen is the US fallback. This install’s address is 127.0.0.1, which is not in the test database. A direct lookup of 81.2.69.160 in that file returns GB.
- The Geo Content block is registered in PHP, but `blocks/geo-content/block.json` points `editorScript` at the handle `rwgc-geo-content-editor`, which is never registered with `index.js`. The editor shows the unsupported-block notice. That code was not changed. Screenshot 1 uses the shortcode and routing meta box instead.

## Checks
- Plugin Check 2.1.0 on the rebuilt WordPress.org zip: 0 errors, 375 warnings.
- `php tests/test-rwgc-wporg-build.php`: passed. Short description 120 characters.
- PHPUnit: 97 tests, 366 assertions, 9 errors, 7 failures. Same baseline as main.
- Neither zip contains `.wordpress-org/`.

## Files changed
- `.wordpress-org/*` icons, banners, screenshots
- `readme.txt` short description, Description opening, screenshot captions
- `reactwoo-geocore.php` header Description

## What was not changed
- Version, tag, release, and `.github/workflows/publish-update.yml`
- No UI or code change to hide the license-key notice
- Block registration left as it is

Previous pass notes follow.

Pull request: https://github.com/reactwoo/reactwoo-geocore/pull/74 (`wporg-build` → `main`). Not merged, not tagged, publish-update.yml untouched.

## Task
Make ReactWoo Geo Core submittable to the WordPress.org Plugin Directory as a packaging variant. Do not merge, tag, release, or change the R2 / api.reactwoo.com publish workflow.

## Choice
A build target, not a fork of the plugin. `RWGC_DISTRIBUTION` stays `reactwoo` in git. `python scripts/package_zip.py --target wporg` rewrites that constant inside the zip only, applies `.distignore`, and drops `includes/class-rwgc-satellite-updater.php`. The default `python scripts/package_zip.py` path is what `.github/workflows/publish-update.yml` already runs, and that path still includes the updater, `docs/`, and `composer.json`.

## Files changed
- `reactwoo-geocore.php` — `Requires at least: 6.2`, `Requires PHP: 8.1`, Plugin URI, Author URI, `RWGC_DISTRIBUTION` default `reactwoo`.
- `includes/functions-rwgc.php` — `rwgc_is_wordpress_org_distribution()`.
- `includes/class-rwgc-plugin.php` — do not load or register the satellite updater on the WordPress.org build.
- `includes/cloud/class-rwgc-cloud-telemetry.php` — telemetry and `rwgc_vid` stay off unless option `rwgc_cloud_telemetry_opt_in` is set. Suggested privacy-policy text.
- `includes/cloud/class-rwgc-cloud-admin.php` — Cloud screen opt-in, off by default, with disclosure.
- `admin/views/settings-page.php` — hide the api.reactwoo.com update check on the WordPress.org build.
- `includes/targeting/class-rwgc-targeting-rule-set-schema.php` and `includes/targeting/class-rwgc-rule-evaluator.php` — request UTM / click-id conditions are free. Audience, weather, and profile conditions still require the separate Pro add-on.
- `includes/class-rwgc-capability-registry.php` — admin copy no longer describes request UTM as a locked Pro feature.
- `blocks/geo-content/block.json`, `blocks/experience-slot/block.json` — `apiVersion` 3 (Plugin Check error on WordPress 7.1).
- `admin/views/visibility-rule-tester-modal.php` — direct-access guard.
- `readme.txt` — external services, Tested up to 7.1, PHP 8.1, short description, trimmed changelog, screenshot placeholders.
- `.distignore`, `scripts/package_zip.py`, `package.json` — WordPress.org zip target.
- `composer.json`, `composer.lock` — declared PHP `>=8.1` to match `vendor/composer/platform_check.php`.
- `CHANGELOG.md` — point older readme history at Git, because `readme.txt` no longer carries every entry.
- `tests/test-rwgc-wporg-build.php`, `tests/test-rwgc-cloud-events.php`.

## What was not changed
- `.github/workflows/publish-update.yml` and any R2 / api.reactwoo.com publish step.
- No version bump, tag, or release.
- `includes/class-rwgc-satellite-updater.php` remains in the tree for the ReactWoo channel.
- Entitlement keys such as `cloud.personalisation` still report allowed only when GeoCore Pro (or a filter) says so. Core does not call `RWGC_Entitlements::allows()` to disable its own geo, shortcode, REST, or block features.
- No secrets committed.

## Commands run and results
- `php -l` on changed PHP files — no syntax errors.
- `php tests/test-rwgc-wporg-build.php` — passed.
- `php tests/test-rwgc-cloud-events.php` — passed, including telemetry off until opt-in.
- `php vendor/bin/phpunit -c phpunit.xml.dist` — Tests: 97, Assertions: 366, Errors: 9, Failures: 7. Same baseline as the previous handoff (attribution “headers already sent”, targeting assistant UI). No new failures.
- PHPCS 3.13.6 + WordPress Coding Standards 3.4.1 on changed PHP files — pre-existing file-comment, alignment, nonce, and test-bootstrap findings. New Cloud admin privacy link uses `esc_url`. Not clean.
- `python scripts/package_zip.py --target wporg` — `reactwoo-geocore-wporg.zip` (356 files). No updater, docs, tests, composer.json, or `pre_set_site_transient_update_plugins`. Distribution constant inside the zip is `wporg`.
- `python scripts/package_zip.py --target reactwoo` — versioned zip still contains the updater, `docs/`, and `composer.json`.
- Plugin Check 2.1.0 via WP-CLI 2.12.0 on WordPress 7.1.3 (MariaDB), against the WordPress.org zip: exit 0, 58 errors, 383 warnings after the block `apiVersion` and modal guard fixes. No updater error. `missing_composer_json_file` is a warning and is intentional.

## Plugin Check errors (follow-up on the same PR)
Status: done. Errors on the WordPress.org zip are 0. Version was not bumped. `publish-update.yml` was not edited. The ReactWoo zip still contains the updater and `RWGC_DISTRIBUTION` `reactwoo`.

Before (Plugin Check 2.1.0, WordPress 7.1.3, same zip as the first pass): 58 errors, 382 warnings. The first PR note said 383 warnings; a fresh count of that zip was 382.

After: 0 errors, 375 warnings.

Fixes: translators comments on placeholder strings, `esc_html()` around contract exception messages, `esc_html__()` / `absint()` for `wp_dropdown_pages()`, `wp_parse_url()`, `wp_delete_file()`, `gmdate()`. `move_uploaded_file()` and `suppress_filters => true` keep a one-line `phpcs:ignore` with a reason (PHP upload move into a fixed MaxMind path; editor status must not be rewritten by other plugins' post filters). `wp_unslash()` on visitor IP headers and the telemetry cookie, an `isset` on the returning-visitor cookie, and a justified ignore on `load_plugin_textdomain()` (still required for the ReactWoo channel) cleared 7 warnings.

Standalone tests that run without WordPress now stub `wp_parse_url()`, `esc_html()`, and `wp_unslash()` where those calls are new.

`php vendor/bin/phpunit -c phpunit.xml.dist` — Tests: 97, Assertions: 366, Errors: 9, Failures: 7. Same baseline as main.

## Remaining warnings
375, grouped by code:
- 264 `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` (view locals shared with the including admin class)
- 37 `WordPress.Security.NonceVerification.Recommended`
- 28 `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`
- 19 `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
- 7 `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` (public `geocore_product_tab_*` hooks and LiteSpeed's `litespeed_vary_add`)
- 6 `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound` (`rw_geo_*` public API)
- 6 `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude`
- 3 `WordPress.PHP.DevelopmentFunctions.error_log_error_log`
- 2 `WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound`
- 1 `missing_composer_json_file` (intentional; runtime needs `vendor/`)
- 1 `WordPress.PHP.DevelopmentFunctions.error_log_var_export`
- 1 `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in`

Screenshot files for the readme captions are not in the repo.
