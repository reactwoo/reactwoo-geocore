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
