<?php

declare(strict_types=1);

use Uganda\Geo\PointInPolygon;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 4 || !is_numeric($argv[1]) || !is_numeric($argv[2]) || !is_file($argv[3])) {
    fwrite(STDERR, "Usage: php tools/geo/locate-point.php LONGITUDE LATITUDE polygons.geojson\n");
    exit(2);
}

try {
    $longitude = (float) $argv[1];
    $latitude = (float) $argv[2];
    $json = json_decode((string) file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($json) || ($json['type'] ?? null) !== 'FeatureCollection' || !isset($json['features']) || !is_array($json['features'])) {
        throw new UnexpectedValueException('Expected a GeoJSON FeatureCollection');
    }

    $locator = new PointInPolygon();
    $matches = [];
    foreach ($json['features'] as $index => $feature) {
        if (!is_array($feature) || !isset($feature['geometry']) || !is_array($feature['geometry'])) {
            throw new UnexpectedValueException('Missing polygon geometry at feature ' . $index);
        }
        $state = $locator->classify($longitude, $latitude, $feature['geometry']);
        if ($state !== 'outside') {
            $matches[] = [
                'feature_index' => $index,
                'source_feature_id' => $feature['id'] ?? null,
                'classification' => $state,
            ];
        }
    }

    echo json_encode(['longitude' => $longitude, 'latitude' => $latitude, 'candidates' => $matches], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
