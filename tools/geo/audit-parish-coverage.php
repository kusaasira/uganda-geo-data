<?php

declare(strict_types=1);

use Uganda\Data\CanonicalIntegrityValidator;
use Uganda\Geo\ArcGisFeatureImporter;
use Uganda\Geo\ParishGeometryCoverageAuditor;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Data/CanonicalIntegrityValidator.php';
require_once $root . '/src/Uganda/Geo/GeometryContract.php';
require_once $root . '/src/Uganda/Geo/ArcGisFeatureImporter.php';
require_once $root . '/src/Uganda/Geo/ParishBoundaryMatcher.php';
require_once $root . '/src/Uganda/Geo/ParishGeometryCoverageAuditor.php';

if (count($argv) < 4 || count($argv) > 6) {
    fwrite(STDERR, "Usage: php tools/geo/audit-parish-coverage.php <features.geojson> <snapshot.json> <reviewed-decisions.json> [unresolved-ids.json] [object-id-field]\n");
    exit(2);
}

try {
    $read = static function (string $path): string {
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException('Cannot read: ' . $path);
        }
        return $content;
    };

    $features = (new ArcGisFeatureImporter())->parsePage($read($argv[1]), $argv[5] ?? 'FID');
    $snapshot = json_decode($read($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    $decisions = json_decode($read($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    $unresolved = isset($argv[4]) ? json_decode($read($argv[4]), true, 512, JSON_THROW_ON_ERROR) : [];
    if (!is_array($snapshot) || !is_array($decisions) || !is_array($unresolved)) {
        throw new RuntimeException('Snapshot, decisions and unresolved IDs must be JSON arrays/objects');
    }
    $validation = (new CanonicalIntegrityValidator())->validate($snapshot);
    if (!$validation['valid']) {
        throw new RuntimeException('Canonical snapshot failed integrity validation');
    }
    $report = (new ParishGeometryCoverageAuditor())->audit($features, $snapshot, $decisions, $unresolved);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Parish coverage audit failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
