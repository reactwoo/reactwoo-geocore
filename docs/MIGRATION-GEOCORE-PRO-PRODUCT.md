# Migration map: GeoElementor Pro → GeoCore Pro

**Status:** Phase 1 in progress (license + copy + builder parity wiring).  
**Do not** rename the `geo-elementor` plugin folder or delete data until Phase 4+.

## Executive summary

| Today | Target |
|--------|--------|
| **Geo Core** (`reactwoo-geocore`) | **GeoCore Free** — engine, country targeting, shared rule JSON, visibility library CPT |
| **GeoElementor Pro** (license slug `geo-elementor`, plugin `geo-elementor/`) | **Retired as a product name** — capabilities move to **GeoCore Pro** |
| **ReactWoo GeoCore Pro** (`reactwoo-geocore-pro`, prefix `RWGCP_`) | **GeoCore Pro** — commercial plugin (already exists); owns advanced targeting + Google/weather |
| **Geo Elementor** plugin | **Free Elementor adapter** — element/geo_rule UI, variant groups, city add-on; no paid “Pro” SKU |

**Critical finding:** `reactwoo-geocore-pro` already registers `rwgc_pro_enabled` but uses `__return_true` on boot. Advanced targeting was incorrectly gated by `EGP_Geocore_Bridge` → Geo Elementor license (`geo-elementor` slug).

---

## Repository inventory

| Path | Role | Rename? |
|------|------|---------|
| `reactwoo-geocore/` | GeoCore Free | Keep; customer name “GeoCore” / “ReactWoo Geo Core” |
| `reactwoo-geocore-pro/` | GeoCore Pro | Keep folder `reactwoo-geocore-pro` (matches existing CI/R2 slug); display “GeoCore Pro” |
| `geo-elementor/` | Elementor adapter + legacy Pro license UI | **Phase 4:** optional rename to `reactwoo-geo-elementor` for clarity; **not** the Pro product |
| `react-license`, `reactwoo-api` | Entitlements, updates | Phase 5: add `reactwoo-geocore-pro` entitlement; map `geo-elementor` keys |

---

## License and feature flags (target)

| Flag | Owner | Meaning |
|------|--------|---------|
| `rwgc_pro_enabled` | GeoCore Pro bootstrap | Valid GeoCore Pro licence (JWT / token) |
| `rwgc_advanced_targeting_enabled` | GeoCore Pro (same gate) | Multi-condition builder, library apply, device/UTM/page version, etc. |
| ~~`egp_is_pro_user`~~ | Legacy only | BC during migration; do not use in new code |
| ~~`EGP_Geocore_Bridge`~~ | Deprecated | Legacy `geo-elementor` licence → advanced targeting until server mapping ships |

---

## Phased rollout

### Phase 1 — Licence truth (non-destructive)

1. `RWGCP_Bootstrap`: licence-aware `rwgc_pro_enabled` + `rwgc_advanced_targeting_enabled`.
2. `EGP_Geocore_Bridge`: legacy fallback only + deprecation docblock.
3. Geo Core customer strings: GeoCore Pro (not Geo Elementor Pro).
4. Elementor element panel: country targeting for all; advanced behind GeoCore Pro.

### Phase 2 — Builder parity

Post-level Gutenberg targeting, shared mounts, library picker everywhere.

### Phase 3 — Geo Elementor de-productization

Remove customer-facing Pro licence; redirect to GeoCore Pro setup.

### Phase 4 — Repo rename (optional)

Keep `reactwoo-geocore-pro` as canonical Pro artefact.

### Phase 5 — Licence server and R2

Map `geo-elementor` licence slug → `reactwoo-geocore-pro`.

---

## Data preserved (no destructive migration)

- `geo_rule` CPT and all `egp_*` meta
- Elementor settings JSON (`egp_*`, `rwgc_*`)
- `rwgc_visibility_rule` library
- Block attributes `portableTargeting`, `usePortableTargeting`

---

## Acceptance checklist

- [ ] No customer UI says “GeoElementor Pro”
- [ ] Advanced targeting unlocked by GeoCore Pro
- [ ] Elementor and Gutenberg parity when Pro active
- [ ] Free = country-only in both builders
- [ ] Legacy `geo-elementor` licences work during migration window

See full audit tables in git history / follow-up PR for per-file line references.
