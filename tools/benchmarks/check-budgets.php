<?php

declare(strict_types=1);

use Uganda\Release\PerformanceBudget;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/location-index.php');
$output = [];
$status = 0;
exec($command . ' 2>&1', $output, $status);
if ($status !== 0) {
    fwrite(STDERR, implode("\n", $output) . "\n");
    exit(1);
}

try {
    $metrics = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($metrics)) {
        throw new UnexpectedValueException('Benchmark output must be a JSON object');
    }
    $defaults = ['constructor_ms' => 1500, 'index_warmup_ms' => 1000, 'memory_peak_bytes' => 268435456];
    $limits = [];
    foreach ($defaults as $key => $default) {
        $setting = getenv('UGEO_MAX_' . strtoupper($key));
        if ($setting !== false && (!is_numeric($setting) || (float) $setting <= 0)) {
            throw new InvalidArgumentException('Invalid benchmark threshold: ' . $key);
        }
        $limits[$key] = $setting === false ? $default : (float) $setting;
    }
    $errors = (new PerformanceBudget())->violations($metrics, $limits);
    fwrite(STDOUT, json_encode(['metrics' => $metrics, 'budgets' => $limits, 'violations' => $errors],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    if ($errors !== []) {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
