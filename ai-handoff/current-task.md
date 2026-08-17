# Current task

**Gate D** — live authoring loop. See `docs/architecture/gate-d.md`.

Production Decision Cloud: `https://decision.reactwoo.com`.
Geo Core **1.8.157** defaults the Cloud Connector to that host and lists pairing under **Integrations → ReactWoo Cloud**.

Local WordPress still uses `wp-content/mu-plugins/reactwoo-local-decision-cloud.php` → `http://127.0.0.1:3040/api/v1`. Do not add an mu-plugin on staging/production for Decision Cloud.

## Do now (staging.aplenty.co.uk)

1. Update Geo Core to 1.8.157.
2. Confirm API base is `https://decision.reactwoo.com/api/v1`.
3. Portal: generate a pairing code (10 minutes).
4. WordPress: **Integrations → ReactWoo Cloud** → paste token → Connect. Leave `management_mode = local`.
5. Author Audience + Experience + Variant, publish-check, publish.
6. Sync manifest. Load a qualifying frontend URL.
7. Confirm cached manifest still applies with Cloud unreachable. No Cloud HTTP on render.

Do not enable `REACTWOO_CLOUD_BRIDGE_ENABLED` on reactwoo.com until Gate D is signed off and Cloud SKUs are mapped.
