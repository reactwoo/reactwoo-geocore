# Cursor output — critical bug hunt (2026-08-23)

## Status

**done** — no new critical correctness bugs on tip `bbe1e69` / Geo Core **1.8.163**.

## Scope

Under-reviewed areas from automation brief: REST, uninstall/updater, option/post races, SQL, unserialize/eval, admin-ajax/admin_post, entitlements, Cloud pairing/credentials, manifest store, component renderer XSS, commits **1.8.157–1.8.163**.

## Files inspected (representative)

- `includes/class-rwgc-rest.php`
- `includes/decision/class-rwgc-request-decision.php`, `class-rwgc-decision-runtime.php`, `class-rwgc-decision-condition-evaluator.php`
- `includes/cloud/*` (pairing, sync, manifest store, http, admin, credentials, migration, event queue, telemetry)
- `includes/entitlements/*`
- `includes/slots/*`, `includes/variants/*`, `includes/components/class-rwgc-php-html-component-renderer.php`
- `includes/integrations/elementor/class-rwgc-elementor-experience-slots.php`
- `includes/integrations/gutenberg/class-rwgc-gutenberg-experience-slot.php`
- `includes/targeting/class-rwgc-assistant-target-service.php`
- `uninstall.php`, `includes/class-rwgc-satellite-updater.php`
- `docs/architecture/gate-d.md`

## What was **not** changed

No product code edits; no new PR (nothing critical to fix).

## Commands run

- `git rev-parse HEAD`, `git log`, `git show` on `7a28e8c`, `c3df855`, `5acc1ea`
- Repo-wide grep for REST routes, admin_post, wp_ajax, SQL, update_option hotspots
- Attempted local PHP repro of Decision `in []` (PHP CLI not on PATH in Cloud VM)

## Result

**NO_NEW_CRITICAL** — aligns with hunts on 2026-08-21/22 on the same tip.

## Near-misses (do not re-file as critical without product sign-off)

| Area | Verdict | Notes |
|------|---------|-------|
| Decision Runtime `in []` / `not_in []` / empty condition group | Near-miss | Same fail-open family as visibility Rule Evaluator; Gate D (1.8.160) makes it visitor-visible for Cloud slots but not a new root cause |
| Elementor slot `before_render` → `register()` → `update_option` | Near-miss | Lost-update / perf; default content preserved |
| Manifest kept after disconnect | Intentional | Gate D contract |
| Entitlements OR-grant (1.8.161) | No visitor impact | No in-plugin `allows()` callers on render path |
| Experiment `visitor_id` not wired in Request_Decision | Near-miss | Assigns control bucket; telemetry has cookie |
| REST AI draft / Variant Manager `edit_pages` without `edit_post` | Near-miss | IDOR-ish read/metadata only |
| Uninstall partial wipe | By design | Leaves CPT/meta/cloud options |
| LiteSpeed vary + Gate D slots | Known #55 | Amplified blast radius, same fix |

## Remaining errors

None (hunt-only pass).
