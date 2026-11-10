<?php

declare(strict_types=1);

if ($argc !== 3 || !is_file($argv[1]) || !is_dir($argv[2])) {
    fwrite(STDERR, "Usage: php tools/ci/prepare-gis-archive-fixture.php source.geojson exported-directory\n");
    exit(2);
}
try {
    $geometry = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($geometry) || ($geometry['type'] ?? null) !== 'FeatureCollection'
        || !is_array($geometry['features'] ?? null) || count($geometry['features']) !== 2) {
        throw new RuntimeException('Expected two real GeoJSON geometry fixtures');
    }
    $fields = [
        'unit_id' => 'Canonical parish identifier',
        'parent_id' => 'Canonical parent unit identifier',
        'source_id' => 'Fixture source registry identifier',
        'src_obj_id' => 'Fixture object identity',
        'evid_date' => 'Fixture match date',
    ];
    $metadata = [
        'crs' => 'EPSG:4326',
        'feature_count' => count($geometry['features']),
        'source_sha256' => hash_file('sha256', $argv[1]),
        'publication_rights' => 'review_required',
        'topology_status' => 'fixture_only',
    ];
    foreach (['fields.json' => $fields, 'metadata.json' => $metadata] as $name => $data) {
        $bytes = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents(rtrim($argv[2], '/') . '/' . $name, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Unable to generate fixture ' . $name);
        }
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
