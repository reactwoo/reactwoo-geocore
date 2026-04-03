# Geo Suite shell — hooks and services

Geo Core ships an **onboarding + workflow shell** (`RWGC_Suite_Admin`, Suite Home, Getting Started, guided variant creation). Satellite plugins stay optional; integration is via filters/actions below.

## Actions

| Hook | When |
|------|------|
| `rwgc_loaded` | Geo Core finished bootstrapping (existing). |
| `rwgc_suite_activity_logged` | `( $item )` — after a row is stored for Suite Home “Recent activity”. |
| `rwgc_variant_created` | `( $result )` — after `RWGC_Variant_Manager::create_country_variant()` succeeds. `variant_page_id`, `master_page_id`, `edit_url`, `country_iso2`. |

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
