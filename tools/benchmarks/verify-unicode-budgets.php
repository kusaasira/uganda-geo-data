<?php

declare(strict_types=1);

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php verify-unicode-budgets.php PROFILE.json\n");
    exit(2);
}
try {
    $result = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Invalid Unicode performance profile JSON\n");
    exit(2);
}
if (!is_array($result)
    || !in_array($result['scenario'] ?? null, [
        'unicode_exact_global', 'unicode_exact_scoped',
        'unicode_prefix_global', 'unicode_prefix_scoped',
        'fuzzy_global', 'fuzzy_scoped',
    ], true)
    || !is_string($result['fingerprint'] ?? null)
    || strlen($result['fingerprint']) !== 64) {
    fwrite(STDERR, "Missing Unicode profile identity or workload\n");
    exit(2);
}
foreach ([
    'iterations', 'first_result_count', 'memory_peak_reserved_bytes',
    'first_query_ms', 'warm_p50_ms', 'warm_p95_ms', 'warm_p99_ms', 'warm_mean_ms',
] as $key) {
    if (!isset($result[$key]) || !is_numeric($result[$key])
        || !is_finite((float) $result[$key]) || $result[$key] < 0) {
        fwrite(STDERR, "Invalid Unicode performance measurement $key\n");
        exit(2);
    }
}
if ($result['iterations'] < 5 || $result['iterations'] > 100
    || $result['warm_p50_ms'] > $result['warm_p95_ms']
    || $result['warm_p95_ms'] > $result['warm_p99_ms']) {
    fwrite(STDERR, "Incomplete or inconsistent Unicode latency measurements\n");
    exit(2);
}

$scenario = $result['scenario'];
$isGlobal = str_ends_with($scenario, '_global');
$isFuzzy = str_starts_with($scenario, 'fuzzy_');

// Cold startup is separately budgeted from a warmed index to avoid hiding
// oversized first-use index construction inside a warm-only benchmark.
$firstLimitMs = $isGlobal ? 3500.0 : 500.0;
$warmLimitMs = $isFuzzy ? ($isGlobal ? 500.0 : 35.0) : ($isGlobal ? 50.0 : 12.0);
$memoryLimitBytes = 167772160; // 160 MiB with optional Unicode indexes
if ($result['first_query_ms'] > $firstLimitMs
    || $result['warm_p95_ms'] > $warmLimitMs
    || $result['memory_peak_reserved_bytes'] > $memoryLimitBytes
    || $result['first_result_count'] < 1) {
    fwrite(STDERR, sprintf(
        "%s: first %.3fms (limit %.3f), warm p95 %.3fms (limit %.3f), peak %.1fMiB: FAIL\n",
        $scenario,
        $result['first_query_ms'],
        $firstLimitMs,
        $result['warm_p95_ms'],
        $warmLimitMs,
        $result['memory_peak_reserved_bytes'] / 1048576
    ));
    exit(1);
}
echo sprintf(
    "%s: first %.3fms, p95 %.3fms, peak %.1fMiB: budgets passed\n",
    $scenario,
    $result['first_query_ms'],
    $result['warm_p95_ms'],
    $result['memory_peak_reserved_bytes'] / 1048576
);
