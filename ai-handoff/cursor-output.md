# Cursor output

## Status
done

Follow-up on PR #74: version 1.9.0, readme free-feature copy, updater stub, `.wordpress-org` assets. Not tagged. publish-update.yml untouched.

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
