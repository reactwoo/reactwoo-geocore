# Gutenberg Experience Slot block (WP7)

Block name: **`reactwoo/experience-slot`**

Uses `block.json` + `InnerBlocks`. Default InnerBlocks markup is always Gate B fallback.

## Attributes

| Attribute | Role |
|-----------|------|
| `slotName` | Human label |
| `slotId` | Stable Core slot ID (filled on save) |
| `managementMode` | `local` \| `managed` |
| `instanceId` | Per-block binding key (`gutenberg:{instanceId}`) |

## Behaviour

1. Editor creates `instanceId` on insert; InspectorControls edit name/mode.
2. `save_post` walks blocks, registers slots, rewrites cloned/missing IDs into post content.
3. `render_block` passes HTML through `reactwoo_render_experience_slot()`.
4. Missing/unavailable slots keep the saved InnerBlocks HTML.

## Manual checks

1. Insert **ReactWoo Experience Slot** → add a paragraph → name the slot → Update → confirm Slot ID in inspector and Geo Core → Experience Slots.
2. Duplicate the block → Update → new Slot ID.
3. Frontend: native content unchanged; wrapper has `data-reactwoo-slot-id`.
