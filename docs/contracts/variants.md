# Variants

Types: `default` | `content` | `reactwoo_component` | `native_reference`.

```json
{
  "id": "variant_b",
  "type": "reactwoo_component",
  "payload": {
    "component": "hero",
    "props": { "heading": "…" }
  }
}
```

Convenience input keys (`content`, `component`+`props`, `native_reference`) map into `payload` on parse. Invalid type → `RWGC_Contract_Exception`.

**Runtime (WP9):** see [variant-engine.md](./variant-engine.md) for Resolver / Renderer / Gate C.
