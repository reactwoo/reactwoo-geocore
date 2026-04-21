# Geo Suite shell — hooks and services

Geo Core ships an **onboarding + workflow shell** (`RWGC_Suite_Admin`, Suite Home, Getting Started, guided variant creation). Satellite plugins stay optional; integration is via filters/actions below.

## Actions

| Hook | When |
|------|------|
| `rwgc_loaded` | Geo Core finished bootstrapping (existing). |
| `rwgc_suite_activity_logged` | `( $item )` — after a row is stored for Suite Home "Recent activity". |
| `rwgc_variant_created` | `( $result )` — after `RWGC_Variant_Manager::create_country_variant()` succeeds. `variant_page_id`, `master_page_id`, `edit_url`, `country_iso2`. |
| `rwgc_matched_experience_profile` | `( $matched, $candidates, $context )` — after runtime profile resolution completes (Core/Satellites can observe match outcomes). |

## Filters

| Filter | Args | Purpose |
|--------|------|---------|
| `rwgc_register_modules` | `( $modules )` | Add/replace rows in `RWGC_Module_Registry::get_registered_modules()`. Each row: `id`, `label`, `description`, optional `active`, `admin_url`, `install_url`, `is_active_callback`. |
| `rwgc_readiness_rows` | `( $rows, $goal )` | Adjust computed readiness list for Suite Home / Getting Started. |
| `rwgc_workflow_launchers` | `( $launchers )` | Cards on Suite Home / Getting Started: `id`, `title`, `description`, `url`, `primary` (bool), `icon` (dashicons class), optional `requires`. |
| `rwgc_workflow_launchers_for_goal` | `( $launchers, $goal )` | After Getting Started reorders launchers to match the wizard goal. |
| `rwgc_goal_guidance` | `( $out, $goal )` | `$out` is `headline` + `body` copy on Getting Started when a goal is selected. |
| `rwgc_suite_activity_providers` | `( $providers )` | Array of **callables**; each returns `array` of activity rows (`type`, `time`, `payload.title`, optional `payload.url`). Merged with stored activity, sorted by `time` desc. |
| `rwgc_suite_activity` | `( $list )` | Final merged activity newest-first before render. |
| `rwgc_next_steps` | `( $steps, $context, $ctx )` | Recommended links after a workflow. Core defines `variant_created`; satellites can add steps for other contexts. |
| `rwgc_routing_overview_rows` | `( $rows )` | Rows for **Page versions** (`RWGC_Variant_Manager::get_routing_overview_rows()`). |
| `rwgc_inner_nav_items` | `( $items, $current )` | Geo Core inner nav (existing). Suite adds Suite Home + Getting Started. |
| `rwgc_context_attribution` | `( $attribution )` | Final normalized attribution payload (`source`, `medium`, `campaign`, `content`, `term`, `gclid`, first/session touch, audiences). |
| `rwgc_profile_match_candidates` | `( $candidates, $context )` | Provide profile candidates before matching. Intended for GeoCore Pro/Cloud-synced profile bundles. |
| `rwgc_matched_experience_profile` | `( $matched, $candidates, $context )` | Select a single matched profile for the active request context. |
| `rwgc_analytics_targets_configured` | `( $configured )` | Signals whether analytics-backed targets are operational; drives availability and admin status messaging. |

## Services (PHP)

- `rwgc_get_suite_handoff_request_context()` — returns `active`, `from`, `launcher`, `variant_page_id` from the current admin request (for satellite screens). Filter: `rwgc_suite_handoff_request_context`.
- `RWGC_Onboarding::get_state()` / `update_state()` — wizard progress (`rwgc_onboarding_state` option).
- `RWGC_Onboarding::log_activity()` — append to `rwgc_suite_activity`.
- `RWGC_Module_Registry::get_readiness_rows( $goal )` — environment checklist.
- `RWGC_Workflows::get_launchers()` — task-first deep links.
- `RWGC_Workflows::get_goal_guidance( $goal )` — short copy for the wizard goal.
- `RWGC_Workflows::order_launchers_for_goal( $launchers, $goal )` — reorder launchers on Getting Started.
- `RWGC_Workflows::get_next_steps( $context, $ctx )` — e.g. `variant_created` + result payload from variant manager.
- `RWGC_Variant_Manager::create_country_variant( $master_id, $iso2, $mode )` — creates draft page + `RWGC_Routing` meta (same rules as the page meta box).

## Activation redirect

On activation, option `rwgc_activation_redirect` is set. On next load of `index.php` or `plugins.php` in admin, the user is redirected once to **Getting Started** (`rwgc-getting-started`).

## Handoff query parameters

Suite workflow buttons append: `rwgc_handoff=1`, `rwgc_from=suite`, `rwgc_launcher={id}`. After creating a variant, **Geo AI** / **Geo Optimise** next-step URLs may also include `rwgc_variant_page_id={id}` so satellites can open the relevant page context.

## Satellite updates (API + R2)

Private builds are published to R2 with metadata in Redis; WordPress calls **`POST /api/v5/updates/check`** and receives a time-limited **`download_url`**.

**Geo Core (free)** uses catalog slug **`reactwoo-geocore`**, **`attach_bearer_token` false** (client does not send `Authorization`). The API **allowlists** that slug in **`UPDATES_FREE_SLUGS`**; JWT is still required for other slugs when **`UPDATES_REQUIRE_LICENSE_TOKEN=true`**. CI publishes Core with the same **`/api/v5/updates/publish`** flow as paid plugins.

**Commercial** plugins send **`Authorization: Bearer`** (license JWT). The API enforces JWT for those slugs when **`UPDATES_REQUIRE_LICENSE_TOKEN`** is on.

**`RWGC_Satellite_Updater`** registers Geo Core plus each satellite that calls **`register()`**.

| Plugin | `catalog_slug` | License JWT |
|--------|----------------|-------------|
| Geo Core | `reactwoo-geocore` | No (free slug on API) |
| Geo AI | `reactwoo-geo-ai` | Yes |
| Geo Optimise | `reactwoo-geo-optimise` | Yes |
| Geo Commerce | `reactwoo-geo-commerce` | Yes |

Filter **`rwgc_satellite_updater_items`** can adjust registered configs. **Geo Elementor** uses its own updater in `geo-elementor/includes/plugin-updater.php`; it prefers **`egp_license_access_token`** and falls back to **`RWGC_Platform_Client::get_access_token()`** when that option is empty.
