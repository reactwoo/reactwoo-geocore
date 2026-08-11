# Audiences

```json
{
  "id": "aud_uk_paid_mobile",
  "name": "UK Paid Mobile",
  "conditions": {
    "all": [
      { "capability": "geo.country", "operator": "equals", "value": "GB" },
      { "capability": "visitor.device", "operator": "equals", "value": "mobile" }
    ]
  }
}
```

Groups use exactly one of `all` / `any` and may nest. Class: `RWGC_Contract_Audience`, `RWGC_Contract_Condition`, `RWGC_Contract_Condition_Group`.
