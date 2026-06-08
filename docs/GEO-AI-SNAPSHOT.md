# Geo Core — site intelligence snapshot

Compact, non-PII metadata describing geo targeting configuration for **Geo AI cloud workflows**. This is **not** page content, Elementor JSON, or visitor personal data.

## Public API

```php
$snapshot = rwgc_build_ai_snapshot( $context = array() );
$hash     = rwgc_get_ai_snapshot_hash();
```

- **`rwgc_build_ai_snapshot()`** — full normalized payload including `snapshot_hash`
- **`rwgc_get_ai_snapshot_hash()`** — SHA-256 of the current snapshot without requiring callers to retain the full array

Requires `RWGC_AI_Snapshot_Builder` (loaded with Geo Core AI snapshot module).

## Schema version

Current: **`schema_version: 1`** (`RWGC_AI_Snapshot_Schema::VERSION`)

Top-level keys (v1):

| Key | Content |
|-----|---------|
| `schema_version` | Integer schema version |
| `generated_at_gmt` | ISO-8601 build time |
| `snapshot_hash` | SHA-256 of normalized payload (excludes this field from hash input) |
| `site` | URL, name, language, timezone, multisite, pro flag |
| `plugins` | Relevant plugin versions (Geo suite, Woo, Elementor) |
| `modules` | Enabled Geo Core modules |
| `target_providers` | Portable targeting provider registry summary |
| `rules` | Visibility / routing rules (ids, types, countries — not content) |
| `conditions` | Condition catalog reference |
| `variants` | Variant pages (master_id, slug, status — not builder data) |
| `parent_pages` | Master page references |
| `popups` | Popup rules summary |
| `forms` | Form hooks summary |
| `tracking_events` | Configured tracking event slugs |
| `conversion_events` | Conversion goal references |
| `relationships` | Variant ↔ rule ↔ popup links |

## Privacy and size

- **Excluded fields** at any depth: emails, API keys, license keys, tokens (see `RWGC_AI_Snapshot_Schema::default_excluded_fields()`)
- **No** post content, Elementor JSON, or HTML bodies
- Satellites may append compact rows via **`rwgc_ai_snapshot_payload`** — same privacy rules apply

## Admin preview

**Geo Core → Tools** (or dedicated AI snapshot preview screen when enabled): inspect normalized JSON and hash before cloud sync.

Sync status: `RWGC_AI_Snapshot_Sync_Status` records last build hash/time for diagnostics.

## Consumers

| Consumer | Usage |
|----------|-------|
| **Geo AI** `RWGA_Site_Intelligence_Sync` | Uploads snapshot to `POST /api/v5/geo-ai/sites/:id/snapshot` |
| **Geo AI** `RWGA_Workflow_Intelligence` | Embeds snapshot in `payload.site_intelligence` for remote workflows |
| **reactwoo-api** | Stores Redis copy; intelligence runner reads rules/variants/popups arrays |

## Testing

- PHPUnit: `tests/Ai/RWGCAiSnapshotTest.php` (when PHPUnit available)
- CLI smoke: `tests/test-rwgc-ai-snapshot.php`

## Related

- Intelligence layer plan: `reactwoo-api/docs/PLAN-GEO-AI-INTELLIGENCE.md`
- Geo Core Phase 5 (variant draft bridge): `docs/phases/phase-5.md`
