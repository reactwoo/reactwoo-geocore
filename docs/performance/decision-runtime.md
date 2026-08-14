# Decision Runtime performance (WP19)

Local evaluation only. **Zero Cloud HTTP** on the visitor page-render path (`template_redirect` onward). Pairing, sync, heartbeat, and event flush stay on admin / cron / WP-CLI.

## What was measured

Bottlenecks were identified from the runtime, then optimised:

| Cost | Finding | Change |
|------|---------|--------|
| Audience scan | Every audience in the manifest was matched even when no candidate experience used it | Evaluate only audiences referenced by **candidate** experiences (status + schedule + optional `slot_id`) |
| Repeated audience match | Same audience reused across slots | Per-request match cache (`RWGC_Decision_Runtime::reset_request_cache()`) |
| Condition trees | AND/OR always walked every child | Short-circuit in `RWGC_Decision_Condition_Evaluator` |
| Context providers | Eager facts for unused capabilities | Lazy resolvers on `RWGC_Contract_Context::with_resolvers()`; resolve once per context |
| Expensive providers | Weather / remote-backed facts could run twice | `RWGC_Context_Value_Cache` + `RWGC_Decision_Context_Factory` (`reactwoo_decision_context_resolvers`) |
| Manifest JSON | `from_array()` on every `current()` | Request-scoped parse memo by revision |
| Cloud HTTP | Accidental visitor-path calls | `RWGC_Cloud_Http` returns `cloud_http_forbidden_on_render` after `template_redirect` and does not increment `attempt_count` |

No extra database queries were added to evaluate. Manifests stay in options; visitor evaluate reads the memoized object.

## Benchmark (CLI, in-memory manifest)

1 live experience-audience pair, 4 slots, **N unused** audiences whose conditions would resolve weather if evaluated. Fresh context per N. Machine: local Windows PHP CLI (2026-08-14).

| N unused audiences | ms | audiences evaluated | weather resolves | remote |
|--------------------|----|---------------------|------------------|--------|
| 1 | 0.065 | 1 | 1 | 0 |
| 10 | 0.018 | 1 | 1 | 0 |
| 50 | 0.016 | 1 | 1 | 0 |
| 100 | 0.017 | 1 | 1 | 0 |

Times are micro-benchmarks, not production TTFB. The invariant is **evaluate cost stays flat as unused audiences grow**: 100 unused audiences still evaluate **one** audience, resolve weather **once**, and make **zero** remote calls. The test asserts 100-audience evaluate under 50ms on this runner.

Reproduce:

```bash
composer test:decision-perf
```

## Debug fields

With `debug => true` or `?rwgc_decision_debug=1` (capability-gated):

- `audiences_total` — audiences in the manifest
- `audiences_evaluated` — cache misses (candidate audiences only)
- `remote_calls` — delta of `RWGC_Cloud_Http::attempt_count()` during evaluate (must be 0)
- `context_resolves` — lazy `Context::get()` resolver invocations
- `elapsed_ms`

Compact (non-debug) results still include `elapsed_ms`, `remote_calls`, `audiences_evaluated`.

## Behaviour note

`matched_audiences` lists audiences **needed by candidate experiences**, not every matching audience in the manifest. Unused audiences are not evaluated.

## Lazy context

```php
$ctx = RWGC_Decision_Context_Factory::for_request( array( 'geo.country' => 'GB' ) );
```

Satellites register expensive facts with `reactwoo_decision_context_resolvers` (capability ID => callable). Wrap remote/DB work in `RWGC_Context_Value_Cache::remember()`.
