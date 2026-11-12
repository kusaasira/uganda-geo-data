<?php

declare(strict_types=1);

use Uganda\Release\LatencySummary;
use Uganda\Uganda;
use Uganda\Village;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$iterations = (int) ($argv[1] ?? 1000);
if ($argc > 2 || $iterations < 50 || $iterations > 100000) {
    fwrite(STDERR, "Usage: php tools/benchmarks/lookup-latency.php [iterations: 50..100000]\n");
    exit(2);
}

$constructedAt = hrtime(true);
$geo = new Uganda();
$constructorMicroseconds = (hrtime(true) - $constructedAt) / 1000;

$villages = $geo->villages();
$village = reset($villages);
unset($villages);
if (!$village instanceof Village) {
    fwrite(STDERR, "The bundled geography contains no villages\n");
    exit(1);
}

$id = $village->id();
$name = $village->name();
$parentId = $village->parishId();
$geo->findById('village', $id);
$geo->searchByName('village', $name, $parentId);
$geo->childrenOf('village', $parentId);
$geo->ancestorsOf('village', $id);

$operations = [
    'find_by_id' => static fn () => $geo->findById('village', $id),
    'name_with_parent' => static fn () => $geo->searchByName('village', $name, $parentId),
    'children_of' => static fn () => $geo->childrenOf('village', $parentId),
    'ancestors_of' => static fn () => $geo->ancestorsOf('village', $id),
];
$result = [];

foreach ($operations as $label => $operation) {
    $samples = [];
    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $start = hrtime(true);
        for ($repeat = 0; $repeat < 20; $repeat++) {
            $operation();
        }
        $samples[] = (hrtime(true) - $start) / 20000;
    }
    $result[$label] = (new LatencySummary())->summarize($samples);
}

fwrite(STDOUT, json_encode([
    'unit' => 'microseconds_per_operation',
    'iterations' => $iterations,
    'repetitions_per_sample' => 20,
    'constructor_microseconds' => $constructorMicroseconds,
    'memory_peak_bytes' => memory_get_peak_usage(true),
    'operations' => $result,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
