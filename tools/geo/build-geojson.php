<?php

declare(strict_types=1);

use Uganda\Geo\VerifiedGeoJsonBuilder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 7) {
    fwrite(STDERR, "Usage: php tools/geo/build-geojson.php features.geojson canonical.json reviewed-decisions.json output.geojson OBJECT_ID_FIELD SOURCE_ID\n");
    exit(2);
}

try {
    $source = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $snapshot = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    $decisions = json_decode((string) file_get_contents($argv[3]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($source) || ($source['type'] ?? null) !== 'FeatureCollection'
        || !isset($source['features']) || !is_array($source['features'])
        || !is_array($snapshot) || !is_array($decisions)) {
        throw new UnexpectedValueException('Expected a GeoJSON FeatureCollection, canonical snapshot and reviewed decisions');
    }

    $index = [];
    foreach ($source['features'] as $feature) {
        if (!is_array($feature) || !is_array($feature['properties'] ?? null)) {
            throw new UnexpectedValueException('Invalid GeoJSON feature properties');
        }
        $id = $feature['properties'][$argv[5]] ?? null;
        if (!is_int($id) && !is_string($id)) {
            throw new UnexpectedValueException('Missing source feature object ID');
        }
        if (!ctype_digit((string) $id) || isset($index[$id])) {
            throw new UnexpectedValueException('Duplicate or invalid source feature ID');
        }
        $index[$id] = $feature;
    }

    $document = (new VerifiedGeoJsonBuilder())->build($index, $snapshot, $decisions, $argv[6]);
    $out = json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $outputPath = $argv[4];
    $directory = dirname($outputPath);
    if (!is_dir($directory) || !is_writable($directory)
        || file_exists($outputPath) || is_link($outputPath)) {
        throw new RuntimeException('Output directory must exist and destination must be unused');
    }

    $temp = tempnam($directory, '.uganda-geo-');
    if ($temp === false) {
        throw new RuntimeException('Cannot create a temporary GeoJSON output');
    }
    try {
        if (file_put_contents($temp, $out) !== strlen($out)) {
            throw new RuntimeException('Cannot write complete GeoJSON output');
        }
        // Hard-linking the fully written file is atomic and never overwrites
        // an existing destination even if another process creates one.
        if (!link($temp, $outputPath)) {
            throw new RuntimeException('GeoJSON destination exists or is not writable');
        }
    } finally {
        unlink($temp);
    }
    fwrite(STDOUT, "Verified polygon collection generated; rights require review before public distribution\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
