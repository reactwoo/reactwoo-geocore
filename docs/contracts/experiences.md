# Experiences & slots

**Experience** — who / where / what / when / measure:

```json
{
  "id": "exp_summer",
  "name": "UK Summer Campaign",
  "audience_id": "aud_uk_paid_mobile",
  "slot_id": "slot_home_hero",
  "variant_id": "variant_b",
  "status": "active",
  "priority": 50,
  "schedule": { "starts": "", "ends": "" },
  "experiment_id": "",
  "goal_id": "goal_purchase"
}
```

**Experience Slot:**

```json
{
  "id": "slot_home_hero",
  "name": "Homepage Hero",
  "page": "/",
  "adapter": "elementor",
  "status": "active",
  "variant_types": ["content", "reactwoo_component", "native_reference"],
  "metadata": {}
}
```

CamelCase aliases (`audienceId`, `slotId`) accepted on input; snake_case on output.
