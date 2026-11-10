<?php

declare(strict_types=1);

use Uganda\Geo\VerifiedGeoJsonBuilder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 6) {
    fwrite(STDERR, "Usage: php tools/geo/build-geojson.php features.geojson reviewed-match-report.json output.geojson OBJECT_ID_FIELD SOURCE_ID\n");
    exit(2);
}

try {
    $source = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $report = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($source) || ($source['type'] ?? null) !== 'FeatureCollection'
        || !isset($source['features']) || !is_array($source['features']) || !is_array($report)) {
        throw new UnexpectedValueException('Expected a GeoJSON FeatureCollection and matching report');
    }

    $index = [];
    foreach ($source['features'] as $feature) {
        $id = $feature['properties'][$argv[4]] ?? null;
        if (!is_int($id) && !is_string($id)) {
            throw new UnexpectedValueException('Missing source feature object ID');
        }
        if (!ctype_digit((string) $id) || isset($index[$id])) {
            throw new UnexpectedValueException('Duplicate or invalid source feature ID');
        }
        $index[$id] = $feature;
    }

    $document = (new VerifiedGeoJsonBuilder())->build($index, $report, $argv[5]);
    $out = json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $temp = $argv[3] . '.partial-' . getmypid();
    if (file_put_contents($temp, $out) === false || !rename($temp, $argv[3])) {
        throw new RuntimeException('Cannot write verified GeoJSON output');
    }
    fwrite(STDOUT, "Verified polygon collection generated; rights require review before public distribution\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
