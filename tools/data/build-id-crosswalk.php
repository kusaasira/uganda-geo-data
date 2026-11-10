<?php

declare(strict_types=1);

use Uganda\Data\LegacyIdentityCrosswalk;
use Uganda\Import\LegacyJsonImporter;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Import/LegacyJsonImporter.php';
require_once $root . '/src/Uganda/Data/LegacyIdentityCrosswalk.php';

try {
    $sourceDirectory = $argv[1] ?? $root . '/src/Uganda/Data';
    $decisionPath = $argv[2] ?? null;
    $decisions = [];
    if ($decisionPath !== null) {
        $raw = file_get_contents($decisionPath);
        if ($raw === false) {
            throw new RuntimeException('Cannot read identity decisions');
        }
        $decisions = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decisions) || array_keys($decisions) !== array_keys(array_values($decisions))) {
            throw new RuntimeException('Identity decisions must be a JSON list');
        }
    }
    $snapshot = (new LegacyJsonImporter())->import($sourceDirectory);
    $report = (new LegacyIdentityCrosswalk())->build($snapshot, $decisions);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Crosswalk failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
