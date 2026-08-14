# Recommendations (WP20)

Advisory Decision Cloud suggestions. **AI never changes a live customer site.**

```json
{
  "id": "rec_1",
  "status": "proposed",
  "observation": "This site has no Cloud experiences yet.",
  "evidence": { "experience_count": 0, "impressions": 0 },
  "suggested_action": "Create a geo-targeted draft experience, then publish only after review.",
  "proposed_experience": { "name": "Draft: country homepage", "status": "draft" },
  "proposed_variant": null,
  "confidence": { "score": 0.7, "explanation": "…" },
  "provenance": {
    "provider": "reactwoo.rules",
    "model": "heuristic-v1",
    "generated_at": "2026-08-14T10:00:00Z",
    "dataset_hash": "abc123",
    "action": "generate"
  },
  "live": false
}
```

Statuses: `proposed` → `approved` (draft resources only) or `dismissed`.

`proposed_experience` / `proposed_variant` are always stored with `status: draft`. `live` is always `false`.

## Safety

- Dataset sent to any model/provider is sanitized: no email, IP, visitor ids, user agents.
- Generate and approve **do not compile** the site manifest.
- Compiled manifests include only `active` / `scheduled` experiences.
- WordPress caches the list (`rwgc_cloud_recommendations`) on admin/cron sync. Visitor rendering never fetches Cloud.

PHP: `RWGC_Contract_Recommendation`, `reactwoo_cloud_recommendations()`.
