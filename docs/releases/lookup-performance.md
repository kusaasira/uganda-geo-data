# Administrative lookup performance regression gate

Run the independent benchmark locally after installing development dependencies:

    php tools/benchmarks/check-budgets.php

The gate executes the real dataset benchmark in three fresh PHP processes.
It uses median constructor and first-index initialization durations to
reduce CI host scheduling noise and the highest observed peak memory to
avoid hiding allocation regressions.

The original CI baseline on PHP 8.4 measured approximately 171 ms to
construct the hierarchy, 43 ms to warm the ID index and 95 MiB peak memory.
The default ceilings are deliberately above this measured baseline:

- Constructor: 750 ms
- Initial index lookup: 250 ms
- Peak process memory: 160 MiB

These are initial production-quality regression budgets, not service-level
objectives. They do not measure steady-state user request latency or prove
the performance of other population scales.

Set UGEO_MAX_CONSTRUCTOR_MS, UGEO_MAX_INDEX_WARMUP_MS or
UGEO_MAX_MEMORY_PEAK_BYTES to change thresholds in controlled environments.
All thresholds must be positive finite numbers. The command fails when a
sample is missing, a measurement is invalid or an enforced ceiling is
exceeded.

The JSON output includes all individual samples and aggregated metrics so
changes can be reviewed. Tighten budgets only after collecting comparable
baseline measurements on the same CI runner class.

## Query latency distributions

Run `php tools/benchmarks/lookup-latency.php 1000` to collect warm p50/p95/p99 latency for ID, scoped-name, prefix-name, child and ancestor queries. Measurements are recorded in microseconds per operation and uploaded as inspectable CI artifacts. See [warm lookup latency methodology](lookup-latency.md).
