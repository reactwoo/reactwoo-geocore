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

`matched_audiences` is only audiences **referenced by candidate experiences** (status, schedule, optional `slot_id` filter) — not every matching audience in the manifest.

Debug (or compact always): `audiences_evaluated`, `remote_calls`, `context_resolves` (debug), `elapsed_ms`. See [performance/decision-runtime.md](../performance/decision-runtime.md).

## Safety

- No Cloud/HTTP during evaluate (`remote_calls` always 0). After `template_redirect`, `RWGC_Cloud_Http` refuses the request (`cloud_http_forbidden_on_render`) and does not count an attempt.
- Unknown capability / operator / exceptions → condition fails closed; page code must not call this on the critical path until Gate B/C.
- Existing `RWGC_Rule_Evaluator` remains the production matcher for portable rules.
- Condition AND/OR short-circuits; lazy context resolvers run only when `get()` needs them.

## Lazy context

`RWGC_Decision_Context_Factory::for_request( $eager )` plus filter `reactwoo_decision_context_resolvers`. Expensive providers should use `RWGC_Context_Value_Cache::remember()`. Manifest `current()` is memoized per revision for the PHP request.

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
