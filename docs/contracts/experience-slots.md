# Experience Slots (WP5)

Stable named locations where ReactWoo may select alternate content.

## Classes

| Class | Role |
|-------|------|
| `RWGC_Experience_Slot_Registry` | Persist slots (`rwgc_experience_slots` option) |
| `RWGC_Experience_Slot_Resolver` | Lookup; missing/unavailable never throw |
| `RWGC_Experience_Slot_Renderer` | Always fall back to default website content |
| `RWGC_Experience_Slot_Id` | `rw_{slug}_{5chars}` IDs |

## Helpers

```php
reactwoo_register_experience_slot( array(
  'name' => 'Homepage Hero',
  'adapter' => 'elementor',
  'page' => '/',
  'metadata' => array( 'binding_key' => 'elementor:el_abc' ),
) );

reactwoo_render_experience_slot( $slot_id, $default_html, $decision );
```

## Clone safety

If an existing slot ID is registered again with a **different** `metadata.binding_key`, a new ID is generated. Elementor/Gutenberg adapters (WP6–7) must supply unique binding keys per element instance.

## Soft delete

`RWGC_Experience_Slot_Registry::mark_unavailable( $id )` — render falls back to default content.

## Admin

Geo Core → **Experience Slots** diagnostics.

## Gate B

Selecting variant `default` / empty / missing slot returns the default HTML unchanged. Builder adapters must pass the original render as `$default_content`.

## Elementor (WP6)

See [elementor-experience-slots.md](./elementor-experience-slots.md).
