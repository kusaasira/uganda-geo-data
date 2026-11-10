<?php

declare(strict_types=1);

use Uganda\Release\PerformanceBudget;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

try {
    // CI hosts vary in transient load. Use the median of three independent
    // processes for durations, but the worst observed peak for memory.
    $samples = [];
    for ($sample = 0; $sample < 3; $sample++) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/location-index.php');
        $output = [];
        $status = 0;
        exec($command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Location benchmark failed: ' . implode("\n", $output));
        }
        $measurement = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($measurement)) {
            throw new UnexpectedValueException('Benchmark output must be a JSON object');
        }
        $samples[] = $measurement;
    }

    $median = static function (array $values): float {
        foreach ($values as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0) {
                throw new InvalidArgumentException('Invalid benchmark measurement');
            }
        }
        sort($values, SORT_NUMERIC);

        return (float) $values[intdiv(count($values), 2)];
    };

    $metrics = [
        'constructor_ms' => $median(array_column($samples, 'constructor_ms')),
        'index_warmup_ms' => $median(array_column($samples, 'index_warmup_ms')),
        'memory_peak_bytes' => max(array_column($samples, 'memory_peak_bytes')),
    ];
    $defaults = [
        'constructor_ms' => 750,
        'index_warmup_ms' => 250,
        'memory_peak_bytes' => 167772160,
    ];
    $limits = [];
    foreach ($defaults as $key => $default) {
        $setting = getenv('UGEO_MAX_' . strtoupper($key));
        if ($setting !== false && (!is_numeric($setting) || (float) $setting <= 0)) {
            throw new InvalidArgumentException('Invalid benchmark threshold: ' . $key);
        }
        $limits[$key] = $setting === false ? $default : (float) $setting;
    }
    $errors = (new PerformanceBudget())->violations($metrics, $limits);
    fwrite(STDOUT, json_encode([
        'sampling' => 'three_independent_processes_duration_median_memory_maximum',
        'samples' => $samples,
        'metrics' => $metrics,
        'budgets' => $limits,
        'violations' => $errors,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    if ($errors !== []) {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
