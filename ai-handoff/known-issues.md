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

**Likely causes (unproven on Local):** Geo Core duplicating full ISO country SELECT2 / Atomic chips into every widget stack (payload size); uncached library rows under large rule libraries; WHMCS unbounded options; host LiteSpeed timeout.

**Mitigation shipped (Geo Core 1.8.128):** `RWGC_Elementor_Ajax::is_heavy_elementor_ajax()` empties country + library option lists during bulk `get_widgets_config` / `get_document_config`; countries hydrated once via `rwgc-elementor-library-bridge.js` from editor-localised list. Single-widget `editor_get_widget_config` keeps full options.

**Regression after 1.8.129–1.8.133:** Panel spun again. WP Abilities registration (and leftover `_doing_it_wrong` HTML when `WP_DEBUG` is on) can run during `elementor_ajax` and break JSON. Document settings still embedded full ISO / library / `get_pages()` lists on `get_document_config`.

**Mitigation shipped (Geo Core 1.8.134):** Skip WP Abilities category/ability registration on any `elementor_ajax`. Slim document Geo Visibility the same way as widget stacks.

**Still 503 after 1.8.134:** Empty option lists were not enough — the full Geo Visibility / Atomic / Experience Slot control trees were still injected into every widget during `get_widgets_config`.

**Mitigation shipped (Geo Core 1.8.135):** Omit those sections entirely on the bulk path. Props schema for Atomic is kept (save-safe). Full UI returns on `editor_get_widget_config`.

**Still 503 after 1.8.135 (production reactwoo.com):** Confirmed `POST admin-ajax.php` from Elementor `requestWidgetsConfig`. Chrome `runtime.lastError` is an extension, not this 503.

**Mitigation shipped (Geo Core 1.8.136 + WHMCS 1.1.5.3):** Default unknown/refresh widget-config batches to heavy; skip Geo Core editor-boot services; WHMCS stubs on every `elementor_ajax`.

**Still 503 after 1.8.136 (production reactwoo.com):** Console stack is `enqueueFont` → `sendBatch` — Elementor 4.2 flushes the pending `get_widgets_config` batch when fonts enqueue. Same 503, not a new font endpoint. Elementor 4.2 has **no** `editor_get_widget_config` hydration. Bulk `get_widgets_config` still calls `get_stack()` for every add-on widget (Unlimited Elements `ucaddon_*`, etc.). Slimming Geo sections cannot shrink that.

**Mitigation shipped (Geo Core 1.8.137 + Geo Optimise 0.4.93):** Replace Elementor `get_widgets_config` / `refresh_widgets_config` so third-party catalogues skip `get_stack()`; cap large select option maps; omit Optimise goal controls on the heavy path.

**Still 503 after 1.8.137:** Skipping `get_stack()` is too late. Unlimited Elements still `eval()`s and registers every addon on `elementor/widgets/register` (plus DB preload) before the AJAX handler runs.

**Mitigation shipped (Geo Core 1.8.138):** Unhook UE / EA / Jet / Premium Addons widget+control registrars at priority 0 on heavy `elementor_ajax`. Header `X-RWGC-Widgets-Config: 1.8.138; slim` proves the build is live.

**Debug (1.8.139):** `[RWGC_EL_WIDGETS]` error_log + `X-RWGC-El-Debug` headers + option `rwgc_elementor_widget_load_last` (shutdown flush). Default on via option; disable with `rwgc_elementor_widget_load_debug` = 0.

**1.8.139 production log (13 Aug 2026):** Logger booted on heartbeat / empty action / editor HTML. Dozens of parallel ~110–270MB requests, 5–8s each, `update_option` on every shutdown. `get_widgets_config` never appeared. Only useful heavy AJAX: `get_document_config` (UE unhooked, ACPT still left, 112 leftover callbacks, 55 widgets, 2732ms). Geo library rows empty/cheap. 503 is LiteSpeed worker exhaustion, not Geo country lists.

**Mitigation shipped (Geo Core 1.8.140):** Debug boots only on `elementor_ajax`. Unhook `ACPT_Elementor`. Treat `enqueue_google_fonts` as light.

**1.8.140 production log (13 Aug 2026):** `ajax_get_widgets_config_start` at 6033ms; UE+ACPT unhooked; 55 widgets at 6717ms; **no `ajax_done` / no shutdown** — LiteSpeed killed the request during `get_stack()` for kept Elementor/Pro/WHMCS widgets. Concurrent font traces still 2.9s. `get_document_config` finished (3732ms).

**Mitigation shipped (Geo Core 1.8.141):** Time-box stack building (7.5s request / 1.8s loop); skip Atomic, WHMCS, and Pro Woo/Loop/MegaMenu stacks on the bulk path; progress checkpoints; debug only on heavy AJAX.

**1.8.141 production log (13 Aug 2026):** `ajax_progress` n=8 last=`spacer` kept=8 at 7197ms, then silence — no `ajax_done` / no shutdown. Time-box cannot interrupt a hung `get_stack()` (next core widget is `image-box`). `get_document_config` still finishes (~2.8s).

**Mitigation shipped (Geo Core 1.8.142):** If request age ≥ 4s after widget registration, return empty stacks for every widget (`slim-late`). Unhook `RW_WHMCS_Bridge`. Progress log before each `get_stack` on the fast path.

**1.8.142 production log (13 Aug 2026):** `ajax_get_widgets_config_start` at 5503ms → unhook (UE/ACPT/WHMCS) → **no `widgets_registered`, no `late_skip`, no shutdown**. Hang is inside `get_widget_types()` / 112 leftover `elementor/widgets/register` callbacks (Elementor Pro `Module`s). Same hook finishes in ~3s on `get_document_config` because that request is not already 5.5s old. Time-box and late-skip never run.

**Mitigation shipped (Geo Core 1.8.143):** `get_widgets_config` / `refresh_widgets_config` return empty maps and do not call `get_widget_types()`. Header `slim-empty`.

**1.8.143 side effect:** Inspector is empty. Elementor 4.2 has no `editor_get_widget_config` hydrate; document config widgets omit controls until the stack is initialized.

**Mitigation shipped (Geo Core 1.8.144):** On `panel/open_editor/widget`, request `rwgc_get_widget_config` for that widget (+ `common` / `common-optimized`), merge into `widgetsCache`, re-open the panel. Action is light (full Geo controls). UE/ACPT/WHMCS stay unhooked on that request. Bulk path still `slim-empty`.

**1.8.144 production (13 Aug 2026):** Panel spinning again. Log shows orphan `boot` (no action, no shutdown) then `get_document_config` finished in 2s. No `get_widgets_config` / `slim-empty`. Hydrate `addRequest` joined the boot AJAX batch, so one `admin-ajax` ran empty widgets-config **and** `get_widget_types()` — the 1.8.142 503 path.

**Mitigation shipped (Geo Core 1.8.145):** Queue hydrate until `panel/state-ready`; send `immediately` as its own request; skip Cloud/integrations on hydrate; log action on boot.

**1.8.145 production (13 Aug 2026):** `get_widgets_config` boots (`heavy:1 hydrate:0`) then silence — no handler, no shutdown. `get_document_config` 24s later finishes in 1.8s. Hang is after Geo Core boot, during later plugin load / `init`, before Elementor dispatches our empty handler.

**Mitigation shipped (Geo Core 1.8.146):** On widgets-config-only batches, verify the Elementor nonce and `wp_die` the empty Elementor ajax envelope immediately (`slim-early` / `ajax_early_exit`). Do not wait for `wp_ajax_elementor_ajax`.

**1.8.146 production (13 Aug 2026):** Panel loads (`ajax_early_exit` in 5ms). Dropping/selecting a widget throws `Cannot read properties of undefined (reading 'content')` in `setDefaultTab`. Document config widgets have no `tabs_controls`; hydrate never ran (no `rwgc_get_widget_config` in the log). Chrome `runtime.lastError` is an extension.

**Mitigation shipped (Geo Core 1.8.147):** Seed `tabs_controls.content` on `getElementData` / `panel/editor/open`, then hydrate immediately (not gated on a missed `panel/state-ready`).

**1.8.147 production (13 Aug 2026):** Hydrate runs. User selected UE Loop Tabs (`ucaddon_ue_listing_tabs`). Response keys were only `common,common-optimized` because UE was unhooked. Inspector tabs showed `[object Object]` because stub tabs were `{}` not `{ title: "Content" }`.

**Mitigation shipped (Geo Core 1.8.148):** Keep UniteCreator hooked when hydrating `ucaddon_*`. Allow get_stack for that one UE widget. Stub tabs include string titles. Heading hydrate still unhooks UE. Risk: UE eval on that one request may 503 — watch for hydrate without `ajax_single_widget`.

**1.8.148 production (13 Aug 2026):** Panel loads (`ajax_early_exit` 4ms), document config 1.8s, render_widget 1.5s — but **no `rwgc_get_widget_config` at all** and tabs still `[object Object]`. Two root causes: `tabs_controls` values are plain strings (`Controls_Manager::get_tabs()`), so both `{}` and `{ title: … }` stringify to `[object Object]`; and hydrate only fired from `panel/open_editor/widget`, which does not run on every selection path.

**Mitigation shipped (Geo Core 1.8.149):** Tab stubs are strings on both sides, and `getElementData` normalizes any object-shaped tab back to its title. Hydrate also fires from the `panel/editor/open` command wrapper. Hydrate no longer unhooks **any** add-on registrar — an unregistered widget has no stack, which is why UE returned `common,common-optimized` only. `ajax_single_widget` now logs per-key control counts (`widget:count` / `widget:missing`). Risk: UE preload/eval on that one request may 503 — watch for hydrate boot without `ajax_single_widget`.

**Do not retry:** Raising PHP memory/timeout as the primary “fix”; patching without staging invocation counts; blaming Flow without Elementor evidence; more Geo Visibility option-list slimming; more per-widget get_stack time-boxes (cannot interrupt a hung stack); calling `get_widget_types()` and then trying to skip stacks.
