<?php

declare(strict_types=1);

use Uganda\Geo\ArcGisFeatureImporter;
use Uganda\Geo\GeometryContract;
use Uganda\Geo\ParishGeometryReconciler;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Geo/GeometryContract.php';
require_once $root . '/src/Uganda/Geo/ArcGisFeatureImporter.php';
require_once $root . '/src/Uganda/Geo/ParishGeometryReconciler.php';

if (count($argv) !== 5) {
    fwrite(STDERR, "Usage: php tools/geo/reconcile-parishes.php <snapshot.json> <GeoJSON-feature-page> <object-id-field> <decisions.json>\n");
    exit(2);
}
try {
    $snapshot = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $featuresRaw = file_get_contents($argv[2]);
    $decisions = json_decode((string) file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot) || !isset($snapshot['units']) || !is_array($snapshot['units']) ||
        !is_string($featuresRaw) || !is_array($decisions)) {
        throw new RuntimeException('Invalid input snapshot, features or decisions');
    }
    $features = (new ArcGisFeatureImporter())->parsePage($featuresRaw, $argv[3]);
    $report = (new ParishGeometryReconciler())->reconcile($snapshot['units'], $features, $decisions);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit($report['valid'] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Geometry reconciliation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
