# Known issues (debug journal)

> Persistent memory across ChatGPT ↔ Cursor loops. Update when a fix fails or a hypothesis is ruled out.

## How to use

- Add a section per recurring bug or flaky area.
- List **Tried** (what did not work) so Cursor does not repeat it.
- List **Do not retry** for approaches that made things worse.

---

## Template (copy for new issues)

### [Short title]

**Symptoms:**

**Tried:**

**Likely causes:**

**Do not retry:**

---

### Geo Assistant popup resolver — local PHPUnit on Windows agent

**Symptoms:** `composer test` / `vendor/bin/phpunit` fails with `Class "PHPUnit\TextUI\Command" not found` or bash fork errors on some Windows Local Sites shells.

**Tried:** Direct `php vendor/bin/phpunit` — same fatal.

**Likely causes:** Incomplete `vendor/` install or PHP/bash fork instability in Cursor agent shell.

**Do not retry:** Assuming tests passed without running on a machine with working `composer install`.

---

### Elementor Elements panel — widgets-config 503 (suite)

**Symptoms:** Elements panel spins; `admin-ajax.php` `elementor_ajax` / `get_widgets_config` returns HTTP 503.

**Tried (Local reactwoo.local):** Opt-in `RW_ELEMENTOR_CONFIG_DEBUG` instrumentation; file probe of full widget stacks; isolation activating Geo Core / WHMCS / GeoCore Pro. With empty visibility-rule and WHMCS catalogs, all passes returned HTTP 200 (~1.0–1.1s, ~234–237 widgets). No 503 reproduced.

**Likely causes (unproven on Local):** Geo Core uncached `get_rwgc_library_rows()` under large rule libraries; WHMCS unbounded option `get_posts`; Loop Grid inject without per-stack guard if multiple query section IDs fire; host timeout / response size on denser staging data. Support Portal not installed on Local; Flow has no Elementor registration.

**Do not retry:** Raising PHP memory/timeout as the primary “fix”; patching without staging invocation counts; blaming Flow without Elementor evidence.
