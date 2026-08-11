# Elementor Experience Slot adapter (WP6)

Binds Elementor **containers** (and classic **sections**) to the Core Experience Slot API.

## Controls (Advanced tab)

| Setting key | UI |
|-------------|-----|
| `rwgc_use_experience_slot` | Use as Experience Slot |
| `rwgc_experience_slot_name` | Slot name |
| `rwgc_experience_slot_id` | Slot ID (auto on save) |
| `rwgc_experience_slot_cloud_status` | Local / Managed |
| `rwgc_experience_slot_binding` | Hidden binding key |

## Behaviour

1. **Save** — `elementor/editor/after_save` walks the document, registers slots with `binding_key = elementor:{elementId}`, and writes regenerated IDs back to `_elementor_data`.
2. **Clone / paste** — same Slot ID with a new element ID regenerates a new Slot ID (see WP5 registry).
3. **Frontend** — markers `data-reactwoo-slot-id` + class `reactwoo-experience-slot`. Output is buffered and passed through `reactwoo_render_experience_slot()` so Gate B default content always wins when no variant is selected.
4. **Editor** — no output buffering (preview stays native Elementor).

## Manual regression

1. Add a Container → enable Experience Slot → name it → Save → confirm Slot ID appears and Geo Core → Experience Slots lists it.
2. Duplicate the container → Save → confirm a **new** Slot ID (not shared).
3. Frontend: view page → inspect wrapper for `data-reactwoo-slot-id`; visual design unchanged.
4. Toggle slot off → Save → design unchanged; registry row may remain until soft-deleted later.

## Not in WP6

- Widget-level slots (after containers stable)
- Cloud Managed UI
- Variant content authoring (WP8–9)
