<?php

declare(strict_types=1);

use Uganda\Geo\PolygonTopologyAuditor;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php tools/geo/audit-topology.php file.geojson\n");
    exit(2);
}

try {
    $document = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $features = ($document['type'] ?? null) === 'FeatureCollection'
        ? ($document['features'] ?? [])
        : [['geometry' => ($document['type'] ?? null) === 'Feature' ? $document['geometry'] : $document]];
    if (!is_array($features)) {
        throw new UnexpectedValueException('Expected a GeoJSON feature list');
    }

    $auditor = new PolygonTopologyAuditor();
    $results = [];
    $failed = 0;
    foreach ($features as $index => $feature) {
        if (!is_array($feature) || !isset($feature['geometry']) || !is_array($feature['geometry'])) {
            throw new UnexpectedValueException('Missing geometry at feature ' . $index);
        }
        $result = $auditor->audit($feature['geometry']);
        if ($result['status'] === 'failed') {
            $failed++;
        }
        $results[] = ['feature_index' => $index, 'audit' => $result];
    }

    echo json_encode(['feature_count' => count($results), 'failed_count' => $failed, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    exit($failed > 0 ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
