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

### Atomic Flexbox geo never hides on frontend (hooks never registered)

**Symptoms:** US-only (or FR-only) Atomic Flexbox still renders for UK visitors even when chips show the correct country.

**Tried:** Fixing string vs string-array country resolve (1.8.126) — settings were fine; hooks still missing.

**Likely causes:** `register_atomic_nestable_hooks` waited on PHP `elementor/frontend/init`, which is JS-only and never runs. Atomic Twig also skips Elementor wrapper attribute CSS hides.

**Do not retry:** Relying on `elementor/frontend/init` in PHP; CSS-only hide for Twig Atomic containers without `should_render`.

---

### Atomic France content visible from UK (empty countries fail-open)

**Symptoms:** After chips control (1.8.124), content gated to France shows for UK visitors.

**Tried:** Preferring only `get_atomic_settings()` — drops legacy `string` countries when schema is `string-array` only.

**Likely causes:** Elementor `Props_Resolver` returns `null` when `$$type` ≠ schema key; evaluator treats empty countries as match-all.

**Do not retry:** Changing empty-list product rule to fail-closed without product sign-off; forcing ISO CSV text control again.

---

### Elementor geo popup cannot close in matching country

**Symptoms:** Allowed-country popup opens but X / overlay / Escape leaves it stuck on screen.

**Tried:** Session dismiss flags + fallback stop (1.8.x) — reopen stopped, but modal still stuck when force-show used `display:flex !important`.

**Likely causes:** `forceShowModalDom` !important styles not cleared on close; early `return` after `modal.hide()`.

**Do not retry:** Relying on Elementor `hide()` alone after force-show without clearing `!important` inline styles.

---

### Elementor Elements panel — widgets-config 503 (suite)

**Symptoms:** Elements panel spins; `admin-ajax.php` `elementor_ajax` / `get_widgets_config` returns HTTP 503.

**Tried (Local reactwoo.local):** Opt-in `RW_ELEMENTOR_CONFIG_DEBUG` instrumentation; file probe of full widget stacks; isolation activating Geo Core / WHMCS / GeoCore Pro. With empty visibility-rule and WHMCS catalogs, all passes returned HTTP 200 (~1.0–1.1s, ~234–237 widgets). No 503 reproduced.

**Likely causes (unproven on Local):** Geo Core uncached `get_rwgc_library_rows()` under large rule libraries; WHMCS unbounded option `get_posts`; Loop Grid inject without per-stack guard if multiple query section IDs fire; host timeout / response size on denser staging data. Support Portal not installed on Local; Flow has no Elementor registration.

**Do not retry:** Raising PHP memory/timeout as the primary “fix”; patching without staging invocation counts; blaming Flow without Elementor evidence.
