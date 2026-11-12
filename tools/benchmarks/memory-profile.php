<?php

declare(strict_types=1);

use Uganda\Uganda;
use Uganda\Village;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$allowed = ['construct', 'full-index', 'cache-churn', 'stream', 'flatten'];
$scenario = $argv[1] ?? '';
$iterations = $argv[2] ?? '';
if ($argc !== 3 || !in_array($scenario, $allowed, true)
    || !ctype_digit($iterations) || (int) $iterations < 1
    || (int) $iterations > 2500
    || (in_array($scenario, ['flatten', 'stream', 'full-index'], true) && (int) $iterations > 20)) {
    fwrite(STDERR, "Usage: php memory-profile.php SCENARIO ITERATIONS(1..2500; stream/flatten/full-index <=20)\n");
    exit(2);
}
$iterations = (int) $iterations;
$baseline = memory_get_usage(false);
$start = hrtime(true);
$geo = new Uganda();
$constructed = memory_get_usage(false);

$pool = [];
if ($scenario === 'cache-churn') {
    foreach ($geo->iterate('village') as $unit) {
        if (!$unit instanceof Village) {
            throw new RuntimeException('Unexpected model while preparing memory workload');
        }
        $pool[] = $unit;
        if (count($pool) >= 1500) {
            break;
        }
    }
    if ($pool === []) {
        throw new RuntimeException('Bundled snapshot has no villages');
    }
}

$workload = static function () use ($scenario, $iterations, $geo, $pool): int {
    $count = 0;
    if ($scenario === 'construct') {
        return $geo->districts() === [] ? 0 : 1;
    }
    if ($scenario === 'full-index') {
        foreach (['district', 'county', 'subcounty', 'parish', 'village'] as $level) {
            foreach ($geo->iterate($level) as $unit) {
                if (!$unit instanceof \Uganda\District && !$unit instanceof \Uganda\County
                    && !$unit instanceof \Uganda\SubCounty && !$unit instanceof \Uganda\Parish
                    && !$unit instanceof Village) {
                    throw new RuntimeException('Unexpected geography model');
                }
                $geo->findById($level, $unit->id());
                $count++;
                break;
            }
        }
        $geo->searchByName('village', 'Central');
        $geo->searchByPrefix('village', 'cen', null, 5);
        return $count;
    }
    if ($scenario === 'cache-churn') {
        for ($i = 0; $i < $iterations; $i++) {
            $unit = $pool[$i % count($pool)];
            $geo->ancestorsOf('village', $unit->id());
            $geo->searchByName('village', $unit->name(), $unit->parishId());
            $geo->searchByPrefix('village', substr($unit->name(), 0, 3), $unit->parishId(), 3);
            $count++;
        }
        return $count;
    }
    for ($i = 0; $i < $iterations; $i++) {
        if ($scenario === 'flatten') {
            $records = $geo->villages();
            $count += count($records);
            unset($records);
        } else {
            foreach ($geo->iterate('village') as $unit) {
                $count++;
            }
        }
    }
    return $count;
};

$warmupCount = $workload();
gc_collect_cycles();
$afterWarmup = memory_get_usage(false);

$firstCount = $workload();
gc_collect_cycles();
$afterFirst = memory_get_usage(false);

$secondCount = $workload();
gc_collect_cycles();
$afterSecond = memory_get_usage(false);

if ($firstCount !== $secondCount || $warmupCount !== $firstCount) {
    throw new RuntimeException('Memory workload results changed between repetitions');
}

echo json_encode([
    'scenario' => $scenario,
    'iterations' => $iterations,
    'records_visited_per_pass' => $firstCount,
    'php_version' => PHP_VERSION,
    'memory_active_before_constructor_bytes' => $baseline,
    'memory_active_after_constructor_bytes' => $constructed,
    'memory_active_after_warmup_bytes' => $afterWarmup,
    'memory_active_after_first_pass_bytes' => $afterFirst,
    'memory_active_after_second_pass_bytes' => $afterSecond,
    'retained_growth_second_pass_bytes' => max(0, $afterSecond - $afterFirst),
    'memory_peak_active_bytes' => memory_get_peak_usage(false),
    'memory_peak_reserved_bytes' => memory_get_peak_usage(true),
    'elapsed_ms' => round((hrtime(true) - $start) / 1000000, 3),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
