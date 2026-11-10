<?php

declare(strict_types=1);

use Uganda\Data\AdministrativeCountReconciler;
use Uganda\Import\LegacyJsonImporter;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Import/LegacyJsonImporter.php';
require_once $root . '/src/Uganda/Data/AdministrativeCountReconciler.php';

try {
    $source = $argv[1] ?? $root . '/src/Uganda/Data';
    $publicationPath = $root . '/resources/data/ubos-2024-reference.json';
    $raw = file_get_contents($publicationPath);
    if ($raw === false) {
        throw new RuntimeException('Cannot read UBOS reference manifest');
    }
    $reference = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($reference)) {
        throw new RuntimeException('Invalid UBOS reference manifest');
    }
    $snapshot = (new LegacyJsonImporter())->import($source);
    $report = (new AdministrativeCountReconciler())->reconcile($snapshot, $reference);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Reconciliation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
