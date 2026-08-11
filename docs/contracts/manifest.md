# Manifest

Cloud-compiled, Core-cached document. Schema `1.x` only in v1.

```json
{
  "schema": "1.0",
  "reactwoo_schema_version": 1,
  "revision": 142,
  "site": "site_123",
  "audiences": [],
  "experiences": [],
  "variants": [],
  "experiments": [],
  "goals": [],
  "slots": []
}
```

**Forward compatibility:** unknown top-level keys are kept in extras and re-serialised. They do **not** invalidate an otherwise valid manifest. Unsupported major schema (e.g. `2.0`) is rejected. Critical failures: missing `site` / `revision`.
