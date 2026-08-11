# Decision Runtime v1

Local evaluation only. **Not** wired into Elementor/Gutenberg render paths yet (WP5–9).

## API

```php
$result = RWGC_Decision_Runtime::evaluate( $manifest, $context, array(
	'visitor_id' => 'anon…',
	'now'        => time(),
	'debug'      => false,
	'slot_id'    => '', // optional filter
) );
```

`RWGC_Decision_Result` exposes matched audiences, selected experiences/variants, action envelopes, reasons, `elapsed_ms`.

## Safety

- No Cloud/HTTP during evaluate (`remote_calls` always 0).
- Unknown capability / operator / exceptions → condition fails closed; page code must not call this on the critical path until Gate B/C.
- Existing `RWGC_Rule_Evaluator` remains the production matcher for portable rules.

## Precedence

Per slot: status → schedule → audience match → priority (desc) → specificity (desc) → experience id (asc).

## Experiments

`RWGC_Decision_Experiment_Assigner::assign()` — `md5(experimentId + "\0" + visitorId)` bucket; stable across calls.

## Hooks

- `reactwoo_decision_before_evaluate`
- `reactwoo_decision_matched_audiences`
- `reactwoo_decision_result`
- `reactwoo_decision_after_evaluate`

Admin debug: `?rwgc_decision_debug=1` (capability-gated).
