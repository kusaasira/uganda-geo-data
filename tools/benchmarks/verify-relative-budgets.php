<?php

declare(strict_types=1);

// CI regression guard. Keep noise-tolerant limits separate from the evidence
// record; a low-level microbenchmark on a shared runner is not an SLA.
if ($argc !== 3 || !is_file($argv[1]) || !is_file($argv[2])) {
    fwrite(STDERR, "Usage: php verify-relative-budgets.php BASE.json CANDIDATE.json\n");
    exit(2);
}

try {
    $baseline = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $candidate = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Invalid benchmark JSON\n");
    exit(2);
}
if (!is_array($baseline) || !is_array($candidate)
    || !isset($baseline['scenario'], $candidate['scenario'], $baseline['fingerprint'], $candidate['fingerprint'])
    || !is_string($baseline['scenario']) || !is_string($candidate['scenario'])
    || !is_string($baseline['fingerprint']) || !is_string($candidate['fingerprint'])
    || $baseline['scenario'] !== $candidate['scenario']
    || $baseline['fingerprint'] !== $candidate['fingerprint']) {
    fwrite(STDERR, "Benchmark identity or scenario mismatch\n");
    exit(1);
}

$metrics = ['first_query_us', 'warm_mean_us', 'warm_p95_us', 'memory_peak_bytes'];
foreach ($metrics as $metric) {
    if (!isset($baseline[$metric], $candidate[$metric])
        || !is_numeric($baseline[$metric]) || !is_numeric($candidate[$metric])
        || !is_finite((float) $baseline[$metric]) || !is_finite((float) $candidate[$metric])
        || (float) $baseline[$metric] < 0 || (float) $candidate[$metric] < 0) {
        fwrite(STDERR, "Invalid performance measurement: $metric\n");
        exit(2);
    }
}

$scenario = $baseline['scenario'];
$bounds = [
    // Leave two MiB plus 10% allocator variation for cross-run noise.
    'memory_peak_bytes' => $baseline['memory_peak_bytes'] * 1.10 + 2097152,
    // First-access timing on shared runners is especially noisy.
    'first_query_us' => max($baseline['first_query_us'] * 1.75, $baseline['first_query_us'] + 1000),
    // Warm paths must avoid material regressions, with an absolute 0.5us
    // guard band for noisy sub-microsecond operations.
    'warm_mean_us' => max($baseline['warm_mean_us'] * 1.40, $baseline['warm_mean_us'] + 0.5),
];

foreach ($bounds as $metric => $maximum) {
    if ((float) $candidate[$metric] > $maximum) {
        fwrite(STDERR, sprintf(
            "%s %s regression: %.4f exceeds %.4f\n",
            $scenario,
            $metric,
            (float) $candidate[$metric],
            $maximum
        ));
        exit(1);
    }
}

// Emit a nonblocking warning about substantial p95 drift; small sample
// tails are less stable than overall warm means on a hosted runner.
if ((float) $candidate['warm_p95_us'] > max(
    $baseline['warm_p95_us'] * 1.25,
    $baseline['warm_p95_us'] + 0.25
)) {
    fwrite(STDERR, sprintf(
        "%s p95 warning: %.4f -> %.4f us\n",
        $scenario,
        (float) $baseline['warm_p95_us'],
        (float) $candidate['warm_p95_us']
    ));
}

echo "$scenario: relative memory and latency budgets satisfied\n";
