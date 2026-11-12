# Warm lookup latency benchmark

The existing constructor benchmark measures hierarchy construction, first index warmup and process peak memory. Use the additional independent query microbenchmark for **warm** lookup distributions:

```bash
php tools/benchmarks/lookup-latency.php 1000
```

This command samples exact village ID lookups, parent-scoped village name matches, immediate village children and village-to-root ancestry. It warms the index before collecting samples. Each sample executes 20 lookups and records the elapsed time per operation, reported in **microseconds**.

The JSON result includes count, minimum, p50, p95, p99, maximum and arithmetic mean. Percentiles use the nearest-rank definition. Input iteration counts are bounded from 50 to 100000.

A PHP microbenchmark does **not** establish end-to-end HTTP request latency, concurrency behavior, cold-start latency or production service-level objectives. Timings depend on processor, PHP build, opcache, load and selected workload. Compare the same runtime and identical data inputs, run enough repetitions and retain raw outputs when evaluating an optimization.

Do not use fabricated nanosecond guarantees or optimize a benchmark at the expense of source identity, deterministic result ordering, ambiguous names or memory consumption.
