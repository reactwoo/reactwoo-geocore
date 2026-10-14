# Regression and release readiness

Nothing in this pass is tagged, pushed, or submitted to WordPress.org.

## What was run

| Check | Result |
| --- | --- |
| `php -l` on the Core evaluator, schema, and platform bootstrap | No syntax errors |
| `php -l` on Pro advanced conditions, portable targeting, bootstrap, and platform capabilities | No syntax errors |
| `php tests/test-rwgc-rule-evaluator.php` | Passed. A United Kingdom country rule matches. A rule that also requires time of day does not. Returning-visitor and new-visitor rules do not match. Sanitize keeps those stored types, audience rows, and page-version rows. |
| `php tests/test-rwgc-wporg-build.php` | Country ownership assertions passed. Four failures are the existing Windows `.distignore` CRLF checks for `composer.lock`, `docs`, `*.sh`, and `*.tgz`. |

## Scenarios not executed in a browser

These still need a site pass before a release is approved:

- Fresh Core-only install, including Elementor Classic, Atomic V4, and Gutenberg country controls.
- Core with a licensed Pro install, including the shared builder showing Pro fields.
- Core with Pro installed and unlicensed. Advanced rules must stay stored and must not match.
- Core with an active Decision Cloud `cloud.personalisation` grant.
- Licence expiry and restoration without deleting rules.
- Full-page variant routing while Pro is entitled, and no routing when it is not.
- Geo Optimise experience slots.
- A fresh WordPress.org zip and Plugin Check.

## Release decision

Not ready to publish. Review `country-only-free` and `advanced-targeting-ownership` first. `main` stays at Geo Core `v1.9.6` and Geo Core Pro `v0.1.55`.
