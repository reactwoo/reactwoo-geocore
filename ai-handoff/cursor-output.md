# Cursor output — critical bug hunt (v1.8.118 / 934c590)

## Status

**needs-review** — 1 NEW critical candidate (not covered by open PRs #1–#44; distinct from rejected IP spoofing)

## Finding

### Elementor empty `rwgc_route_enabled` silently disables Suite/post-meta page routing

- **Files:** `includes/class-rwgc-routing.php` (`get_page_route_config`), `includes/class-rwgc-elementor.php` (document SWITCHER `rwgc_route_enabled`, default `''`)
- **Trigger:**
  1. Create a country variant via Suite / `RWGC_Variant_Manager` (post meta `_rwgc_route_enabled=1` on master).
  2. Open the master page in Elementor → Document Settings → Advanced → Geo Visibility.
  3. Leave **Enable Page Variant Routing** Off (default) and Update, **or** toggle it On then Off and Update.
  4. Elementor persists `rwgc_route_enabled: ""` in `_elementor_page_settings`.
  5. Visit the master as a matching-country visitor — no redirect.
- **Impact:** Persisted Suite/Gutenberg routing keeps looking enabled in post meta / admin meta box, but frontend `maybe_route_request()` reads `enabled=false` and never redirects. Country variants stop working after ordinary Elementor content edits.
- **Root cause:** `get_page_route_config()` force-disables when Elementor has the key set to empty string, overriding non-empty post meta. Elementor SWITCHER off/default is `''`, so “not using Elementor routing” is indistinguishable from “explicitly disable all routing”.
- **Minimal fix:** Treat Elementor `rwgc_route_enabled === 'yes'` as enable/merge override only. Do **not** force `enabled=false` on empty/absent Elementor values; defer to post meta. If Elementor must be able to disable, require an explicit non-empty off value and/or sync disable into post meta on Elementor save.

## Paths checked (OK, rejected, or already covered)

| Area | Result |
|------|--------|
| IP header spoofing (`RWGC_GeoIP::get_current_ip`) | Rejected per task |
| Assistant REST / create_popup / can_execute / popup retarget | #28 / #33 / #44 |
| Surface evaluator fail-open / empty portable / unresolved library | #31 / #37 |
| Visibility rule repository wipe / wrong-post update | #17 / #18 |
| Elementor library wipe / page_type incompatible | #35 / #42 |
| Popup site-wide inject | #26 |
| Rule Tester preview isolation / reporting-only | #40 / below bar |
| Page Version query spoof | #20 / #21 |
| Gutenberg `hideCountries` / MaxMind settings wipe | #24 |
| Rule builder filtered selection truncation | #5 / #6 |
| Registry↔repository recursion fatal | #25 |
| Document `?elementor-preview` bypass | #16 |
| Unsigned `rwgc_cc` LiteSpeed vary | by-design / rejected |
| Insights AI auth / display_mode | #43 / #41 |
| REST write endpoints + permission callbacks | re-traced; no new bypass beyond #28 |
| Cache_Compat cookie writers | first-visit set / preview isolation = #40 |
| Assistant JS execute beyond can_execute | #33 / #44 only |
| Gutenberg post-geo `sanitize_portable` wipe | same class as #17; lower novelty than routing override |
| Variant applications sync / experience workflow | auth + nonce OK; no new wipe |

## Not changed

No code changes (hunt/report only).

## Commands

- `git rev-parse HEAD` → `934c590`
- `gh pr list` / targeted `rg` + `Read` of repository, REST, admin visibility saves, routing, cache-compat, Elementor/Gutenberg, rule tester preview, assistant JS, variant applications, experience workflow, product meta
