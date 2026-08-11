# Variant Engine (WP9)

Resolves and renders Experience Slot variants with mandatory default-content fallback (Gate C).

## Types

| Type | Class | Payload |
|------|-------|---------|
| `default` | `RWGC_Default_Variant` | (none) |
| `content` | `RWGC_Content_Variant` | `html` or `content.html` |
| `reactwoo_component` | `RWGC_Component_Variant` | `component` + `props` |
| `native_reference` | `RWGC_Native_Reference_Variant` | `reference` |

## Pipeline

1. **Store** — `reactwoo_register_variant()` / option `rwgc_variants` / `reactwoo_lookup_variant` filter  
2. **Resolver** — missing / invalid / incompatible → not ok  
3. **Renderer** — empty / error → default HTML; never throws to visitors  
4. **Slot bridge** — `reactwoo_experience_slot_render_variant` → Variant Engine  

## Diagnostics

`RWGC_Variant_Diagnostics::events()` codes: `default`, `missing`, `invalid`, `incompatible`, `error`.

## Helpers

```php
reactwoo_register_variant( array(
  'id' => 'var_b',
  'type' => 'reactwoo_component',
  'component' => 'hero',
  'props' => array( 'headline' => 'Hello' ),
) );

reactwoo_render_experience_slot( $slot_id, $default_html, $decision );
```

## Native references

Implement `reactwoo_resolve_native_reference` to map a reference string to HTML. Unresolved references fall back to default.

## Gate C

Same slot + same page markup; Decision Runtime selects `default` | `content` | `reactwoo_component` | `native_reference` without editing the page between requests.
