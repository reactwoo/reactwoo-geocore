# Events & goals

**Goal:**

```json
{ "id": "goal_purchase", "type": "commerce.purchase", "value": "revenue" }
```

**Event:**

```json
{
  "type": "goal.purchase",
  "experience": "exp_summer",
  "variant": "variant_b",
  "audience": "aud_uk_paid_mobile",
  "value": 89.99,
  "timestamp": "2026-08-11T07:00:00Z",
  "anonymous_visitor_id": "…"
}
```

`visitor_id` accepted as alias for `anonymous_visitor_id`. No PII fields in the v1 contract.
