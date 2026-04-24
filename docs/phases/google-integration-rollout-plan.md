# Google Integration Rollout Plan (Cloud + GeoCore Pro)

This document captures the agreed architecture and sprint order for introducing Google-aware targeting without overloading GeoCore free.

## Product boundaries

- `reactwoo-geocore` (free): geo detection, routing, rule engine, targeting registry, builder integrations, suite shell.
- `reactwoo-geocore-pro` (premium runtime): attribution persistence, session capture, profile matching, premium operators/diagnostics, cloud sync.
- `react-cloud`: Google OAuth/control plane, account + campaign sync, campaign/profile mapping, site assignment + publish.
- `reactwoo-geo-ai`: profile-aware content generation and recommendation workflows.
- `reactwoo-geo-optimise`: profile/campaign cohort experimentation and winner reporting.

## Why this shape

- GeoCore already owns runtime targeting and should stay WordPress.org valuable.
- Geo AI already owns generation workflows and should consume matched profile context.
- Geo Optimise already owns experiments and should consume profile/campaign segmentation.
- React Cloud already has OAuth proxy patterns and is the right home for Google auth/token lifecycle.

## Shared cross-plugin model

### Experience Profile

- `profile_id`
- `name`
- `conditions`
- `countries`
- `source`
- `medium`
- `campaign`
- `content`
- `device`
- `language`
- `content_strategy`
- `ai_enabled`
- `optimise_enabled`
- `pages`
- `status`
- `version`

### Attribution Snapshot (GeoCore Pro-owned persistence)

- first touch
- session/current touch
- UTM keys
- `gclid`
- timestamp
- landing page id
- matched profile id

## Sprint sequence

1. GeoCore attribution contract + runtime hooks.
2. GeoCore Pro bootstrap (license/cloud client/admin shell).
3. React Cloud Google module (OAuth + campaign/account sync + profile authoring).
4. GeoCore Pro profile matcher (sync, cache, runtime match, debug/simulator).
5. Geo AI profile-aware generation.
6. Geo Optimise profile/campaign cohort experiments.
7. Cloud aggregate reporting across profile/generation/experiments.

## Current execution status

### Started: Sprint 1 (in `reactwoo-geocore`)

- Introduce first-class attribution service:
  - `includes/context/class-rwgc-context-attribution.php`
- Centralize attribution normalization keys:
  - `source`, `medium`, `campaign`, `content`, `term`, `gclid`
  - `returning_visitor`, `analytics_audiences`
  - `first_touch`, `session_touch`
- Replace direct request param reads in analytics provider with attribution context service.
- Add extensibility hook for future Pro/Cloud integrations:
  - `rwgc_context_attribution`

## Next Sprint 1 tasks after this start

- Wire attribution payload into broader context snapshot contract.
- Add explicit profile hook points:
  - `rwgc_profile_match_candidates`
  - `rwgc_matched_experience_profile`
- Add tests for attribution precedence and malformed input handling.
- Expose analytics readiness (`rwgc_analytics_targets_configured`) in admin diagnostics with clearer docs.

## Incremental delivery notes

### React Cloud — site config (control plane)

- `GET /geo-api/v1/site/config` — returns `schema_version`, `profiles`, `mappings` (keyed by license JWT domain in process memory until persistent storage is added).
- `PUT /geo-api/v1/site/profiles` — replace `profiles` (optional `mappings` in body).
- `PUT /geo-api/v1/site/mappings` — replace `mappings` only.

### GeoCore Pro — pointing Pro at React Cloud for config

- Optional PHP constant `RWGCP_API_BASE` (e.g. `https://cloud.reactwoo.com`) plus filter `rwgcp_site_config_path` defaulting to `/api/v5/site/config`; for Cloud direct use `/geo-api/v1/site/config`.
- Filters: `rwgcp_api_base`, `rwgcp_site_config_path`.

### GeoCore Pro — profile simulator

- Settings → GeoCore Pro includes a **Profile simulator** form (synthetic country, device, attribution) and prints the matched profile JSON for the cached profile bundle.
