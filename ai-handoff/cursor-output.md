# Cursor output

## Status
done

Bundled `composer/ca-bundle` is 1.6.0. geoip2 stays v3.4.0. Plugin version is 1.9.19. Not merged and not tagged.

## Files changed
- `composer.lock` and `vendor/composer/ca-bundle/` — 1.5.14 to 1.6.0, plus the Composer metadata that records that package.
- Plugin header, `RWGC_VERSION`, readme stable tag, changelog, and the wporg build guard — 1.9.19.

## Not changed
- Other locked dependencies, including geoip2 v3.4.0.
- Root `composer.json` stays in the wporg zip.
- No tag and no merge.

## Commands
- `composer update composer/ca-bundle` — lock moved only ca-bundle 1.5.14 to 1.6.0.
- `composer install --no-dev --optimize-autoloader` — committed vendor stays production-only.
- `python3 scripts/package_zip.py --target wporg` — zip contains `composer.json`, `composer/ca-bundle` 1.6.0, and `geoip2/geoip2` v3.4.0. No `ext/` C files and no `dev-bin/release.sh`.

## Remaining errors
- None from this change.

## Status
done

Read-only ReactWoo ability tools now default omitted input to an object, matching `core/get-site-info`. A no-argument run was rejected as "input is not of type object" before the callback.

## Files changed
- includes/platform/class-rwgc-wp-abilities-adapter.php — `default` on list and site-context input schemas. Describe still requires `id`.
- tests/test-rwgc-platform-capabilities.php — locks those schema defaults.
- CHANGELOG.md — unreleased note.

## Not changed
- Permission checks, tool ids, capability registration, MaxMind, visitor rendering.

## Commands
- php tests/test-rwgc-platform-capabilities.php — passed (28 assertions)

## Status
done

WordPress 6.9+ Abilities bridge now exposes three read-only admin tools (reactwoo/list-capabilities, reactwoo/describe-capability, reactwoo/site-context) with show_in_rest. Capability ids are not MCP tools. Version 1.9.17, rebased onto origin/main.

## Files changed
- includes/platform/class-rwgc-wp-abilities-adapter.php — three admin tools instead of one ability per capability.
- docs/contracts/capabilities.md, tests/test-rwgc-platform-capabilities.php.

## Not changed
- Satellite capability registration. Decision Cloud. Condition and action execution.

## Commands
- php tests/test-rwgc-platform-capabilities.php — passed before rebase
- php tests/test-rwgc-elementor-ajax.php — passed before rebase

## Status
done

Merged latest main (squash of PR #94, `79a869b`) into the 1.9.16 Plugin Check branch. Both the filesystem fixes and the popup-resolver / attribution test fixes are kept. Not tagged and not uploaded.

## Files changed
- `includes/class-rwgc-settings.php` — `wp_strip_all_tags()` only.
- `includes/class-rwgc-maxmind.php` — WordPress filesystem helpers for directory checks, creation, and cleanup.
- `includes/integrations/elementor/class-rwgc-elementor-ajax.php` — phpcs ignore for the read-only Elementor action check.
- `includes/class-rwgc-rest.php` — phpcs ignore for LiteSpeed's `litespeed_control_set_nocache`.
- `tests/bootstrap.php` — stubs for those WordPress helpers.
- `tests/Engine/RWGC_MaxMindFollowupsTest.php` — missing destination directory, archive extract plus cleanup, and a stubbed download.
- `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` — popup-resolver slices from PR #94.
- `tests/Engine/RWGC_ContextAttributionTest.php` — separate processes from PR #94.
- `tests/test-rwgc-elementor-preview-bypass.php` — CLI stubs from PR #94.
- `tests/test-rwgc-geoip-ip.php` — stub `wp_strip_all_tags()` for the CLI script, which does not load the PHPUnit bootstrap.
- `.github/workflows/test.yml` — Geo Core tests run on `cursor/*` pull requests.
- Version 1.9.16 in the plugin header, `RWGC_VERSION`, readme, changelog, and the wporg build guard.

## Not changed
- `post__not_in` queries.
- Visitor-facing MaxMind, Elementor, or attribution behaviour.
- No merge of this PR, no tag, and no WordPress.org upload.

## Commands
- `vendor/bin/phpunit -c phpunit.xml.dist --filter RWGC_MaxMind` — 59 tests, 221 assertions, OK (before this merge).
- `php tests/test-rwgc-wporg-build.php` — all guards passed.

## Remaining errors
- None from this change.

## Status
done

Geo Core tests on current main failed for the same reasons as the 1.9.14 PR run: popup-resolver PHPUnit checks sliced the wrong JavaScript, three helpers were looked up as `function function …`, and attribution tests called `setcookie()` after PHPUnit had already written output. The assistant behaviour and cookie classification are unchanged. No plugin file and no version bump. Squash-merged to main as `79a869b`.

## Files changed
- `tests/Admin/RWGCTargetingAssistantUiRegressionTest.php` — assert the popup hint, cancel footer, and create-error renderer in the functions that own them; stop doubling the `function` prefix.
- `tests/Engine/RWGC_ContextAttributionTest.php` — run each case in its own process so `setcookie()` happens before any output.
- `tests/test-rwgc-elementor-preview-bypass.php` — stub `absint()`, `wp_unslash()`, `sanitize_key()`, and `get_queried_object_id()` so the logged-out preview assertion can run.
- `.github/workflows/test.yml` — run the suite on every pull request, including `cursor/*`, so a green check means the tests ran.

## Not changed
- `admin/js/rwgc-targeting-assistant.js` and attribution cookie writes.
- Plugin version (still 1.9.15). No tag, no WordPress.org upload.
- Visitor-facing plugin code.

## Commands
- `vendor/bin/phpunit -c phpunit.xml.dist` — 177 tests, 1227 assertions, OK.
- Workflow CLI scripts (`test:rwgc-rule-evaluator`, `test:rules`, `test:library-rule-lookup`, `test:visibility-rule-status`, `test:geoip-ip`, `test:elementor-preview-bypass`, `test:elementor-rule-status`, `test:geo-content-block`) — OK.

## Remaining errors
- None from this change.

## Status
needs-review

A MaxMind download no longer writes the request's cached settings back over an admin save. `RWGC_Settings::get_settings_fresh()` drops the autoloaded option cache before `record_download_success`, `record_error`, `record_manual_upload`, and `adopt_legacy_db` merge their fields. Not tagged.

## Files changed
- `includes/class-rwgc-settings.php` — re-read `rwgc_settings` after deleting the alloptions cache entry.
- `includes/class-rwgc-maxmind.php` — those four writers use the fresh read.
- `tests/Engine/RWGC_MaxMindCacheFlushTest.php` — a license key saved during the request survives the download write.
- `CHANGELOG.md` — unreleased note.

## Not changed
- The download still authenticates with the credentials loaded when the request started.
- No version bump and no tag.

## Commands
- `php phpunit-9.6.phar -c /tmp/phpunit-maxmind.xml --stderr` — 56 tests, 198 assertions, OK. The new test fails if the cache delete is removed (returns `old_key`).

## Remaining errors
- None from this change.

## Status
needs-review

Geo Core 1.9.13 extraction no longer uses an unreadable system temp directory. A failed unpack is a saved error, temporary files are removed, and the live database stays in place. The 401 text stays next to the failure time until a download succeeds. Not merged and not tagged.

## Files changed
- `includes/class-rwgc-maxmind.php` — try/catch Throwable around unpack and listing, work directory under uploads or get_temp_dir(), finally cleanup.
- `includes/class-rwgc-settings.php` — keep a download error when the body is not valid UTF-8.
- `admin/views/integrations-maxmind-page.php` — show the stored error on later loads, beside the failure time.
- `tests/Engine/RWGC_MaxMindFollowupsTest.php` — unlistable temp dir, cleanup, and persistent error message.

## Commands
- `vendor/bin/phpunit -c phpunit.xml.dist --stderr` — 138 tests, 987 assertions, 7 failures in `RWGCTargetingAssistantUiRegressionTest`, 0 errors.

## Remaining errors
- None from this change. The 7 Targeting Assistant UI failures match the known baseline.

## Status
needs-review

Geo Core 1.9.13 schedules MaxMind downloads, shows the credentials-saved notice, keeps the newest database from the archive, and stops the MaxMind screen scrolling sideways on a phone. Not merged and not tagged.

## Files changed
- `includes/class-rwgc-maxmind.php` — twice-weekly cron with jitter, newest dated edition file, reader check, atomic replace, masked key, success and failure times.
- `includes/class-rwgc-plugin.php` — schedule on activation, unschedule on deactivation, register the cron hook.
- `includes/class-rwgc-admin.php` — redirect with `settings-updated`, keep a masked or empty license key, sync the schedule on save.
- `includes/class-rwgc-settings.php` — keep the new download timestamps.
- `admin/views/integrations-maxmind-page.php` — masked key, last success, last failure.
- `admin/css/admin.css` — phone layout for the MaxMind screen.
- `tests/Engine/RWGC_MaxMindFollowupsTest.php` — nine tests. Credential URL assertion updated for the edition.
- Version 1.9.13 in the plugin header, `RWGC_VERSION`, readme, changelog, block assets, and the WordPress.org build assertions.

## Not changed
- Manual MaxMind download still uses Basic auth and drops the Authorization header on the redirect.
- The seven known Targeting Assistant UI failures.
- No tag and no merge.

## Commands
- `vendor/bin/phpunit -c phpunit.xml.dist --stderr` — 135 tests, 968 assertions, 7 failures in `RWGCTargetingAssistantUiRegressionTest`, 0 errors.
- `php tests/test-rwgc-wporg-build.php` — all guards passed.
- `composer install --no-dev --optimize-autoloader` then `python3 scripts/package_zip.py` — `reactwoo-geocore-1.9.13.zip`.

## Remaining errors
- None from this change. The 7 Targeting Assistant UI failures match the known baseline.

## Status
needs-review

Geo Core 1.9.12 cleans and validates MaxMind credentials, keeps them when Settings → General is saved, and explains a 401. Merged with the GeoIP2 3.4.0 update already on main.

## Files changed
- `includes/class-rwgc-maxmind.php` — strip invisible characters, reject a bad Account ID or key before HTTP, and append a plain-language 401 hint.
- `includes/class-rwgc-settings.php` — preserve MaxMind and database keys that the General form does not post, and sanitize credentials.
- `includes/class-rwgc-admin.php` — clear `db_last_error` when the saved pair changes.
- `admin/views/integrations-maxmind-page.php` — turn off autocapitalize, autocorrect, and autofill.
- `tests/Engine/RWGC_MaxMindCredentialsTest.php` — nine tests.

## Not changed
- The MaxMind download URL, Basic auth, and the manual redirect that drops the Authorization header.
- The GeoIP2 3.4.0 update and the WordPress.org zip exclusions already on main.

## Commands
- `vendor/bin/phpunit -c phpunit.xml.dist --stderr` — 126 tests, 924 assertions, 7 failures in `RWGCTargetingAssistantUiRegressionTest`, 0 errors.

## Remaining errors
- None from this change. The 7 Targeting Assistant UI failures match the known baseline.

## Status
needs-review

Geo Core 1.9.12 updates GeoIP2 to 3.4.0 and keeps MaxMind C extension sources out of the WordPress.org zip. Not merged and not tagged. 1.9.10 and 1.9.11 are already tagged on main, so this is 1.9.12.

## Files changed
- `composer.json`, `composer.lock`, `vendor/` — geoip2/geoip2 v3.4.0, maxmind-db/reader v1.14.0, composer/ca-bundle 1.5.14. Production autoload only. maxmind/web-service-common v0.11.1 stays because GeoIP2 3.4 still requires it.
- `includes/class-rwgc-geoip.php` — country lookup reads GeoIP2 3 readonly `isoCode` and `name`.
- `.distignore`, `scripts/package_zip.py` — WordPress.org zip drops `vendor/maxmind-db/reader/ext/` and vendor `.c`/`.h`/`.m4`/`.w32`/`.phpt`/`.neon`/`.toml`/`mise.lock`.
- Version 1.9.12 in the plugin header, `RWGC_VERSION`, readme stable tag, changelog, upgrade notice, block asset files, and the WordPress.org build assertions.

## Not changed
- MaxMind licence-key download and the pure-PHP database reader.
- ReactWoo channel zip still includes the updater and the C extension sources.
- No tag, no merge, no WordPress.org upload.

## Commands
- `php tests/test-rwgc-wporg-build.php` — passed.
- `python3 tests/test-rwgc-wporg-zip-guard.py` — passed.
- `python3 scripts/package_zip.py --target wporg` — created `reactwoo-geocore-wporg.zip`.
- `python3 tests/test-rwgc-wporg-upload-precheck.py` — 44 checks, 0 failed.
- `python3 scripts/package_zip.py --target reactwoo` — updater and `ext/maxminddb.c` still present.
- GeoIP2 3.4 lookup against MaxMind test databases — country GB / United Kingdom, city London, ASN 1221 Telstra Pty Ltd. Plugin `lookup_ip` returned the same country.
- Plugin Check 2.1.0 on the directory zip — 0 errors, 6 warnings (nonce and VIP `exclude` queries). None are in the GeoIP2 upgrade.

## Remaining errors
- None from this change.

## Status
needs-review

Geo Core 1.9.11 lets a collapsed ReactWoo Geo admin menu item navigate on a phone, and keeps the app-shell module nav below the admin bar. Not merged and not tagged.

## Files changed
- `admin/js/rwgc-admin-menu.js` — capture-phase click and touchstart stopPropagation on the collapsed top-level item.
- `includes/class-rwgc-admin-platform.php` — enqueues that script with the `rwgc-admin-menu` handle on every admin screen while the sidebar is collapsed.
- `admin/css/rwgc-app-shell.css` — 46px offset at 600px, border-box module nav at 960px, no right margin at 782px.
- Version 1.9.11 in the plugin header, `RWGC_VERSION`, readme stable tag, changelog, upgrade notice, block asset files, and the WordPress.org build assertions.
- `tests/Admin/RWGCAdminMenuMobileTest.php` — enqueue and CSS/JS presence.

## Not changed
- Flyout `display:none` rules. No merge and no tag.

## Commands
- `php tests/test-rwgc-wporg-build.php` — passed.
- `vendor/bin/phpunit -c phpunit.xml.dist --stderr` — 117 tests, 901 assertions, 7 failures in `RWGCTargetingAssistantUiRegressionTest`, 0 errors.

## Remaining errors
- None from this change. The 7 Targeting Assistant UI failures match the known baseline.

## Status
needs-review

Version is 1.9.10 on the PR #87 branch. The iframe fix is unchanged.

## Files changed
- Plugin header, `RWGC_VERSION`, readme stable tag, changelog, upgrade notice, block asset versions, and the WordPress.org build version assertions.

## Not changed
- `rwgc_geo_content_enforce_embed_safety()` and its tests.

## Commands
- Pending: `php tests/test-rwgc-wporg-build.php`.

## Remaining errors
- None from the version bump.

## Status
needs-review

A rejected iframe opening tag no longer consumes a later `</iframe>`. Geo Content, shortcodes, slots, and Elementor popups keep a following https embed, and an HTML comment around the rejected tag still closes so the rest of the page stays visible. No version bump and no tag.

## Files changed
- `includes/functions-rwgc.php` — `rwgc_geo_content_enforce_embed_safety()` checks each `<iframe>` opening tag on its own.
- `tests/Integrations/RWGCGeoContentEscapePathsTest.php` — unclosed http iframe, and the same tag inside a comment, before a YouTube embed.

## Not changed
- Allowlist, form actions, href checks, and https embeds that are already well formed.
- Version 1.9.9 and tags.

## Commands
- `php /tmp/phpunit.phar --bootstrap tests/bootstrap.php --stderr tests/Integrations/RWGCGeoContentEscapePathsTest.php` — OK (8 tests, 91 assertions).
- `php /tmp/phpunit.phar --bootstrap tests/bootstrap.php --stderr tests/Integrations/RWGCGeoContentBlockTest.php` — OK (11 tests, 400 assertions).
- WordPress 6.7.1 `wp_kses` plus the helper: commented-out http iframe leaves the offer and the YouTube iframe; Google Maps https iframe stays.

## Remaining errors
- None from this change.

## Status
needs-review

Geo Content 1.9.9 uses one allowlist (`rwgc_geo_content_allowed_html`) for the block, the `[rwgc_if]` shortcode, Gutenberg experience slots, and Elementor slots and popups. https iframes and forms stay. Script, `on*` handlers, `srcdoc`, `javascript:` hrefs, and non-https iframes are removed. Non-https iframes are dropped entirely. Not tagged. #86 stays a draft.

## Files changed
- `includes/functions-rwgc.php` — shared allowlist, builder-attribute merge for Elementor, full removal of non-https iframes, href and srcdoc hardening.
- `includes/class-rwgc-shortcodes.php` — `[rwgc_if]` uses `rwgc_kses_geo_content_html`.
- `includes/class-rwgc-gutenberg.php` — block already used that helper.
- `includes/integrations/gutenberg/class-rwgc-gutenberg-experience-slot.php` — slot output uses the same helper.
- `includes/integrations/elementor/class-rwgc-elementor-experience-slots.php` and `class-rwgc-elementor-popups.php` — `rwgc_kses_rendered_html()` now starts from the Geo Content allowlist.
- `tests/Integrations/RWGCGeoContentEscapePathsTest.php` and `RWGCGeoContentBlockTest.php` — embed/form kept; script, onerror, javascript: hrefs, and non-https iframes removed. Hostile filter cannot add script, on* handlers, or srcdoc.
- `tests/bootstrap.php` — test filters can hook `apply_filters`.
- `tests/test-rwgc-gutenberg-experience-slot.php` — loads the helper the slot renderer calls.
- Version 1.9.9 in the plugin header, `RWGC_VERSION`, readme stable tag, changelog, upgrade notice, and block asset files.

## Not changed
- No tag. #86 stays a draft. #85 is closed as superseded, not merged.

## Commands
- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr` — 111 tests, 865 assertions, 7 failures in `RWGCTargetingAssistantUiRegressionTest`, 0 errors.
- `php tests/test-rwgc-wporg-build.php` — passed.
- `php tests/test-rwgc-gutenberg-experience-slot.php` — passed.
- `php tests/test-rwgc-elementor-experience-slots.php` — passed.

## Remaining errors
- None from this change. The 7 Targeting Assistant UI failures match the known baseline.

## Status
needs-review

Geo Content block output still runs through `wp_kses` with the post allowlist, and now also allows https iframes and simple forms. The allowlist filter is `rwgc_geo_content_allowed_html`. Script tags and `on*` handlers are removed after that filter. Version was not bumped and nothing was tagged. PR #85 was left open.

## Files changed
- `includes/functions-rwgc.php` — Geo Content allowlist, `wp_kses` escape, https iframe src check, and the `rwgc_geo_content_allowed_html` filter.
- `includes/class-rwgc-gutenberg.php` — Geo Content render uses that escape instead of `wp_kses_post`.
- `tests/Integrations/RWGCGeoContentBlockTest.php` — YouTube iframe and a simple form survive; script, onclick, and a `javascript:` src are stripped.

## Not changed
- Shortcodes, experience slots, and Elementor popups still use `rwgc_kses_post_html` / `rwgc_kses_rendered_html`.
- No version bump, changelog entry, or tag.
- PR #85 was not edited.

## Commands
- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr tests/Integrations/RWGCGeoContentBlockTest.php` — OK (11 tests, 399 assertions).
- `vendor/bin/phpunit --bootstrap tests/bootstrap.php --stderr` — 105 tests, 786 assertions, 7 failures in `RWGCTargetingAssistantUiRegressionTest`. The 9 `RWGC_ContextAttributionTest` errors did not reproduce in this run (those 9 tests passed).

## Remaining errors
- None from this change. The 7 Targeting Assistant UI failures match the known baseline.

## Status
needs-review

1.9.8 is the release for the country-only copy pass and the admin-shell view-transition fix (`admin/js/rwgc-app-shell.js` aligns the active tab instantly).

## Status
needs-review

Country-only ownership is on local branch `country-only-free`. It is not merged, tagged, pushed, or submitted. Do not merge `wporg-free-features-1.9.7`.

Geo Core evaluates country and country-group rules. Geo Core Pro 0.1.56 on local branch `advanced-targeting-ownership` registers the other resolvers only when `RWGCP_Bootstrap::is_licensed()` is true (standalone licence or Decision Cloud `cloud.personalisation`).

## Files changed
- Core free condition list and `RWGC_Rule_Evaluator::default_resolvers()` are country and country group. Premium eval methods moved to `RWGCP_Advanced_Conditions`.
- Target registry and admin provider lists default to the geo provider. Pro appends language, time, device, weather, analytics, and commerce providers when entitled.
- Platform capability bootstrap registers `geo.country` and `geo.country_group`. Pro registers the advanced capability IDs when entitled.
- Rule builder `FIELD_DEFS` is country and country group. Pro adds `extra_fields` on `rwgc_portable_targeting_editor_context`.
- Saves still keep stored advanced conditions. They do not match until Pro is entitled.

## Not changed
- Visitor context collection still fills language, device, UTM, and related snapshot fields for satellites and Decision Cloud.
- Page-version storage and Geo Optimise experience slots are unchanged.
- Text domain stays `reactwoo-geocore`. Nothing was published.

## Commands
- `php tests/test-rwgc-rule-evaluator.php` — passed
- `php tests/test-rwgc-wporg-build.php` — ownership assertions passed; four known Windows `.distignore` CRLF failures
- `php -l` on the Pro ownership files — no syntax errors

## Previous 1.9.7 note
The rejected branch `wporg-free-features-1.9.7` was pushed without a tag. Its tip exposed premium conditions as free. This pass corrects that.

## Status
done

Plugin Check warning cleanup is on local branch `wporg-plugin-check-warnings`, based on `main` at `0a63aa6` (PR #84). Version stays **1.9.6** because `v1.9.6` is not on origin. Nothing was committed, tagged, pushed, or submitted to WordPress.org.

WordPress.org zip sha256: `1e344983952c5c6032ba204247cef6fbbc0a78db582e7114606ae4d4e4e18474`

## Files changed
- Admin view variables are prefixed `rwgc_`, with aliases in the including methods.
- `rw_geo_*` helpers are now `rwgc_*`, and the old names call `_deprecated_function`.
- Product-tab hooks are `rwgc_product_tab_*`. The old `geocore_product_tab_*` hooks still fire through `do_action_deprecated`. LiteSpeed's `litespeed_vary_add` is unchanged, with a phpcs ignore.
- `assets/css/rw-components.css` loads on Geo Core admin screens and on the front only when a Geo Core block, shortcode, widget, or component render needs it.
- Removed the four unignored `error_log` / `var_export` debug lines in GeoIP and MaxMind.
- Read-only `$_GET` uses and the 13 `meta_query` lookups have justified phpcs ignores. MaxMind and wizard `$_POST` reads sit after `check_admin_referer()`.
- `scripts/package_zip.py` reads the finished zip before asserting it, so Python 3.13 can open the archive.

## Not changed
- No `post__not_in` query exists in this tree, so none was rewritten.
- PHPUnit was not run. `vendor/phpunit` does not contain `PHPUnit\TextUI\Command`.
- Plugin Check 2.1.0 was not run locally. WordPress 6.2 and current wp-admin were not activated in a browser.

## Commands
- `php tests/test-rwgc-geoip-ip.php` — passed
- `php tests/test-rwgc-components.php` — passed
- `php tests/test-rwgc-page-variant-ownership.php` — passed
- `php tests/test-rwgc-wporg-build.php` — the four known Windows `.distignore` CRLF failures only
- `python tests/test-rwgc-wporg-upload-precheck.py` — 44 checks, 0 failed
- `python scripts/package_zip.py --target wporg` — `reactwoo-geocore-wporg.zip`

## Status
done

Geo Core 1.9.6 addresses the remaining WordPress.org review items on branch `cursor/wporg-review-fixes-b7ed`. Nothing was merged, tagged, pushed to `main`, or submitted to WordPress.org.

## Files changed
- Inline script and style output now uses `wp_enqueue_*` plus `wp_add_inline_script` / `wp_add_inline_style` (visibility rule editor, collapsed admin menu, Elementor popup antiflash).
- Nonces, superglobals, and rendered HTML are sanitized or escaped. MaxMind upload uses `wp_handle_upload`.
- The directory zip includes `composer.json` and excludes `assets/icon-*.png`, `cacert.pem`, and page-version routing. Stored `page_version_url` conditions are preserved and are not offered unless Pro is active.
- Version 1.9.6 in the plugin header, `RWGC_VERSION`, readme stable tag, and changelog. Readme External services still links ReactWoo Terms and Privacy.

## Not changed
- `vendor/composer/ca-bundle` class file stays so Composer autoload remains valid. Only `res/cacert.pem` is omitted from the directory zip.
- Page-version routing classes stay out of this repo. Evaluation still returns false until Geo Core Pro loads `RWGC_Page_Version`.
- PHPUnit baseline is unchanged: 9 errors in `RWGC_ContextAttributionTest` (headers already sent) and 7 failures in `RWGCTargetingAssistantUiRegressionTest`.

## Commands
- `php tests/test-rwgc-rule-evaluator.php`, `php tests/test-rwgc-wporg-build.php`, `php tests/test-rwgc-page-variant-ownership.php`, `php tests/test-rwgc-returning-visitor.php`
- `python3 tests/test-rwgc-wporg-zip-guard.py`
- `php /tmp/phpunit.phar -c phpunit.xml.dist` → Tests: 102, Errors: 9, Failures: 7
- `python3 scripts/package_zip.py --target wporg`
- `python3 tests/test-rwgc-wporg-upload-precheck.py --require-zip` → 44 checks, 0 failed
- Plugin Check 2.1.0 on WordPress 7.1.3, slug `reactwoo-geocore` → 0 errors, 299 warnings
- Activated the built zip on WordPress 6.2 and 7.1.3. Dashboard, visibility rules, MaxMind, setup, settings, add-ons, insights, and tools returned HTTP 200 with no fatal error.

## Remaining errors
- Plugin Check warnings that remain: unprefixed view variables, nonce-recommended on read-only query args, `meta_query` slow-query notes, `error_log` debug lines, and `assets/css/rw-components.css` loaded in all contexts (`EnqueuedStylesScope`). None are errors.
- `cacert.pem` is excluded, not deleted from the git tree.

## Status
done

Geo Core 1.9.5 removes customer-facing GeoElementor cards and the install, MaxMind, and geo_rule compatibility that only existed for that plugin. Geo Core Pro 0.1.55 no longer copies a GeoElementor licence. Elementor country controls still use the stored `egp_*` keys.

## Files changed
- Add-on card, suite card, module row, brand SVG, and the target-types quick action.
- MaxMind autoload no longer looks in a GeoElementor folder. The legacy geo_rule post type is not registered.
- Pro licence migration class and the legacy rules submenu are gone.

## Not changed
- Elementor visibility settings still use `egp_*` control keys. Those are Geo Core's saved Elementor data.
- Licence-server aliases in react-license, reactwoo-api, and Decision Cloud were not changed.
- MaxMind upload still uses `move_uploaded_file`.

## Commands
- `php tests/test-rwgc-page-variant-ownership.php`
- `php -l` on the edited Core and Pro PHP files
- `php tests/test-weather-facets.php` and `php tests/test-page-route-engine.php` (Geo Core Pro)

## Remaining errors
- On this Windows checkout, `.distignore` is CRLF, so three distignore regexes in `test-rwgc-wporg-build.php` fail locally. CI checks out LF.

## Status
done

Geo Core 1.9.3 includes the 1.9.2 telemetry and Geo Content fixes plus page-version ownership. Geo Core Pro 0.1.53 turns page versions on for a standalone licence or Decision Cloud `cloud.personalisation`.

## Files changed
- Version 1.9.3 in the plugin header, `RWGC_VERSION`, readme, changelog, and the Geo Content asset file.
- Dashboard and readme no longer describe a one-page free limit or GeoElementor as the page-version product.
- `docs/architecture/PLAN.md` records that page versions are Geo Core Pro and that a Cloud personalisation grant is the same entitlement, not a second bill.
- Geo Core Pro 0.1.53: `RWGCP_VERSION`, readme Requires PHP 8.1, changelog.

## Not changed
- Routing PHP files still ship in the WordPress.org zip. The org build does not boot them.
- MaxMind upload still uses `move_uploaded_file`.
- Composer vendor dump stash and `scripts/release_commit_tag.py` were not committed.

## Commands
- `php tests/test-rwgc-page-variant-ownership.php`
- `php tests/test-rwgc-wporg-build.php`
- `php tests/test-weather-facets.php` (Geo Core Pro)

## Remaining errors
- On this Windows checkout, `.distignore` is CRLF, so three distignore regexes in `test-rwgc-wporg-build.php` fail locally. The same three failed on the released 1.9.2 tree. CI checks out LF.

## Status
done

Geo Core 1.9.2 release branch merges PR #80 (telemetry opt-out) and PR #79 (Geo Content country list on WordPress 6.2–6.4). The only merge conflict was `ai-handoff/cursor-output.md`; both sections are kept. PR #81 is not included.

## Files changed
- Version 1.9.2 in the plugin header, `RWGC_VERSION`, readme stable tag, changelog, and the Geo Content asset file.
- `tests/test-rwgc-cloud-events.php` — `'no'`, `'off'`, `false`, and `''` stay opted out.

## Not changed
- Telemetry still opts in only for `true`, `1`, or `"1"`. The country-list handle fix is unchanged from #79.
- PR #81 is not in this release.

## Status
done

Anonymous Cloud telemetry stays off when the saved opt-in is `0`. No version bump, no tag.

## Root cause
`RWGC_Cloud_Telemetry::is_opted_in()` cast `get_option()` with `(bool)`. WordPress stores the integer `0` and returns it on the next request as the string `"0"`, which is truthy. Saving the Cloud form with the box unchecked, or unchecking it later, stored `0`, then the next request treated that as consent, checked the box again, and resumed `rwgc_vid` plus event upload.

## Files changed
- `includes/cloud/class-rwgc-cloud-telemetry.php` — opt-in is only `true`, `1`, or `"1"`.
- `includes/cloud/class-rwgc-cloud-admin.php` — the checkbox uses that same reader.
- `tests/test-rwgc-cloud-events.php` — string `0`, int `0`, string `1`, int `1`, and a missing option.
- `tests/test-rwgc-wporg-build.php` — source guard against the boolean cast.
- `CHANGELOG.md` — unreleased note.

## Not changed
- The `rwgc_cloud_telemetry_allowed` filter still overrides the stored choice.
- No version bump, no tag.

## Commands
- `php tests/test-rwgc-cloud-events.php` — all assertions passed.
- `php tests/test-rwgc-wporg-build.php` — all guards passed.

## Remaining errors
- None in these tests.

## Status
done

Geo Content country options follow the editor script handle WordPress actually registered. WordPress 6.5+ still uses `rwgc-geo-content-editor` from the asset file. WordPress 6.2–6.4 registers `reactwoo-geocore-geo-content-editor-script` and previously left the country combobox empty. No version bump, no tag.

## Files changed
- `includes/class-rwgc-gutenberg.php` — read `editor_script_handles` for inline country data and portable context.
- `includes/class-rwgc-targeting-rule-builder-assets.php` — attach the rule builder to that same handle.
- `tests/Integrations/RWGCGeoContentBlockTest.php` — handle normalization and the hardcoded enqueue check.

## Not changed
- Front-end rendering, block save output, MaxMind notices, plugin version.

## Commands
- `php vendor/phpunit/phpunit/phpunit --bootstrap tests/bootstrap.php --stderr tests/Integrations/RWGCGeoContentBlockTest.php` — OK (8 tests, 25 assertions).
- `node tests/test-rwgc-geo-content-block.js` — OK.
- `php tests/test-rwgc-wporg-build.php` — OK.

## Remaining errors
- None in these checks.

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
