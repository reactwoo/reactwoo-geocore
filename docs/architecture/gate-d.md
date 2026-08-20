# Gate D — Cloud authoring loop (local)

**Must demonstrate:** Author in Cloud → Publish → WordPress sync → visitor qualifies → variant shows. Cloud off → the last cached manifest still works. No Cloud HTTP on the visitor render path.

This is a live-site gate, not a new product sprint. Automated coverage already exists in:

- Decision Cloud `tests/api.test.js` — pair → confirm → manifest → 304
- Decision Cloud `tests/portal.test.js` — publish-check
- Geo Core `composer test:cloud-connector` — sync, outage keeps cache, disconnect keeps manifest
- Geo Core `composer test:decision-perf` — Cloud HTTP blocked after `template_redirect`

## Local layout

| Piece | Path |
|-------|------|
| Decision Cloud (Node) | `wp-content/plugins/reactwoo-decision-cloud` (not a WordPress plugin) |
| Geo Core | `wp-content/plugins/reactwoo-geocore` |
| Local API pointer | `wp-content/mu-plugins/reactwoo-local-decision-cloud.php` (Local WordPress → `127.0.0.1:3040` only) |

Live / staging sites use Geo Core’s default `https://decision.reactwoo.com/api/v1`. Do not add a must-use plugin to retarget Decision Cloud on those hosts.

## Run

1. Restart the ReactWoo Local site so nginx picks up the Decision Cloud deny rule.
2. From the Decision Cloud folder: `npm start` (portal `http://127.0.0.1:3040/portal/`).
3. Confirm Geo Core Cloud Connector uses `http://127.0.0.1:3040/api/v1` (the mu-plugin sets this on Local hosts).
4. In the portal, create a pairing code (`#/sites/connect`) and approve it in wp-admin.
5. Author an Audience + Experience + Variant, run publish-check, publish.
6. In wp-admin, sync the manifest (Cloud Connector). Load a qualifying frontend URL and confirm the variant.
7. Stop `npm start`. Reload the same URL — the cached manifest must still apply. The page must not call Cloud.

## Stop conditions

- Pairing must leave `management_mode = local` until an explicit Cloud managed choice.
- `GET /billing` must not call the store. Reconcile is an explicit portal POST.
- Do not mark this gate done from unit tests alone. A live Local page load is required.

## Passed — 2026-08-20 (Local)

Live loop on `http://reactwoo.local/gate-d-loop/` with Geo Core **1.8.160** and Decision Cloud **0.17.9** (`127.0.0.1:3040`).

| Step | Result |
|------|--------|
| Pair | `management_mode = local`, `connected = true` |
| Author | Audience `geo.country` `in` `GB` + slot `rw_gated_hero_abc12` + content variant `GATE-D-LIVE-UK` |
| Sync | Manifest revision 5, 1 experience |
| Visitor (Cloud up) | `.page-content` rendered `<p>GATE-D-LIVE-UK</p>` (HTTP 200) |
| Cloud stopped | `127.0.0.1:3040` connection refused |
| Visitor (Cloud off) | Same URL still rendered `<p>GATE-D-LIVE-UK</p>` |
| Visitor-path HTTP | Request-time eval `http_attempts = 0` |

Rank Math meta description still quotes stored inner-block default `NATIVE-DEFAULT`. That is SEO excerpt of the saved Gutenberg default, not the rendered slot.

This does **not** enable production `REACTWOO_CLOUD_BRIDGE_ENABLED`.

## Request-time contract (Geo Core 1.8.160+)

WordPress slots read `reactwoo_current_decision_result`. `RWGC_Request_Decision` evaluates the **cached** manifest with `RWGC_Decision_Runtime` and visitor context (`geo.country` from `rwgc_get_visitor_country()`).

- Missing cache → `null` → default website content.
- Cloud HTTP is forbidden after `template_redirect` and is not used by this provider at all.
- Portal-authored conditions may use `type`/`op`; Core accepts those as `capability`/`operator`. Decision Cloud 0.17.9+ also normalises them at compile time.
- Slot IDs must match `rw_[a-z0-9]+(?:_[a-z0-9]+)*_[a-z0-9]{5}`. Copy the Gutenberg/Elementor slot ID into the Cloud experience (or the Cloud-generated `rw_*` ID into the block).
