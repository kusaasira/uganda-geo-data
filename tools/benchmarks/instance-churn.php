<?php

declare(strict_types=1);

use Uganda\Uganda;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 2 || !ctype_digit($argv[1])
    || (int) $argv[1] < 2 || (int) $argv[1] > 30) {
    fwrite(STDERR, "Usage: php instance-churn.php ITERATIONS(2..30)\n");
    exit(2);
}
$iterations = (int) $argv[1];
$initial = memory_get_usage(false);
$samples = [];
$start = hrtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $geo = new Uganda();
    if ($geo->districts() === []) {
        throw new RuntimeException('Loaded an unexpectedly empty geography snapshot');
    }
    unset($geo);
    gc_collect_cycles();
    $samples[] = memory_get_usage(false);
}

$result = [
    'scenario' => 'instance-churn',
    'iterations' => $iterations,
    'active_baseline_bytes' => $initial,
    'active_samples_after_release_bytes' => $samples,
    'active_last_bytes' => $samples[count($samples) - 1],
    'retained_growth_bytes' => max(0, max($samples) - $initial),
    'memory_peak_reserved_bytes' => memory_get_peak_usage(true),
    'elapsed_ms' => round((hrtime(true) - $start) / 1000000, 3),
];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
