<?php

declare(strict_types=1);

use Uganda\Geo\ArcGisFeatureImporter;
use Uganda\Geo\ParishBoundaryMatcher;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Geo/GeometryContract.php';
require_once $root . '/src/Uganda/Geo/ArcGisFeatureImporter.php';
require_once $root . '/src/Uganda/Geo/ParishBoundaryMatcher.php';
if (!isset($argv[1], $argv[2], $argv[3])) {
    fwrite(STDERR, "Usage: php tools/geo/match-parishes.php <feature-collection.json> <canonical-snapshot.json> <decisions.json> [object-id-field]\n");
    exit(2);
}
try {
    $read = static function (string $path): string {
        $body = file_get_contents($path);
        if ($body === false) {
            throw new RuntimeException("Cannot read: $path");
        }
        return $body;
    };
    $features = (new ArcGisFeatureImporter())->parsePage($read($argv[1]), $argv[4] ?? 'FID');
    $snapshot = json_decode($read($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    $decisions = json_decode($read($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot) || !is_array($decisions) ||
        array_keys($decisions) !== array_keys(array_values($decisions))) {
        throw new RuntimeException('Snapshot must be an object and decisions must be a list');
    }
    $report = (new ParishBoundaryMatcher())->match($features, $snapshot, $decisions);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Parish matching failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
