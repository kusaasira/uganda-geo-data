<?php

declare(strict_types=1);

use Uganda\Uganda;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

$start = microtime(true);
$uganda = new Uganda();
$loaded = microtime(true);
$memoryAfterLoad = memory_get_usage(true);

$district = $uganda->findById('district', 1);
$indexed = microtime(true);
$memoryAfterIndex = memory_get_usage(true);

if ($district === null) {
    fwrite(STDERR, "The bundled dataset does not contain district ID 1\n");
    exit(1);
}

fwrite(STDOUT, json_encode([
    'constructor_ms' => round(($loaded - $start) * 1000, 2),
    'index_warmup_ms' => round(($indexed - $loaded) * 1000, 2),
    'memory_after_load_bytes' => $memoryAfterLoad,
    'memory_after_index_bytes' => $memoryAfterIndex,
    'memory_peak_bytes' => memory_get_peak_usage(true),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
