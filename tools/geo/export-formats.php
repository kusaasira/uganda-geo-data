<?php

declare(strict_types=1);

use Uganda\Geo\GeometryRoundTripVerifier;
use Uganda\Geo\GeometryExportFields;
use Uganda\Geo\ImmutableFileWriter;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tools/geo/export-formats.php verified.geojson NEW_OUTPUT_DIRECTORY\n");
    exit(2);
}
$input = realpath($argv[1]);
$destination = $argv[2];
if ($input === false || !is_file($input) || file_exists($destination) || is_link($destination)
    || !is_dir(dirname($destination))) {
    fwrite(STDERR, "Input must exist and output directory must not exist\n");
    exit(2);
}
$stage = $destination . '.partial-' . bin2hex(random_bytes(5));

function executeGdal(array $arguments): void
{
    $command = implode(' ', array_map('escapeshellarg', $arguments));
    $output = [];
    $status = 0;
    exec($command . ' 2>&1', $output, $status);
    if ($status !== 0) {
        throw new RuntimeException('GDAL conversion failed: ' . implode("\n", $output));
    }
}

function removeStage(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($path);
}

try {
    $data = json_decode((string) file_get_contents($input), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new InvalidArgumentException('Invalid GeoJSON input');
    }
    $exporter = new GeometryExportFields();
    $prepared = $exporter->prepare($data);
    if (!mkdir($stage, 0700) || !mkdir($stage . '/shapefile', 0700)) {
        throw new RuntimeException('Unable to create isolated export stage');
    }
    ImmutableFileWriter::write(
        $stage . '/parishes.geojson',
        json_encode($prepared, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"
    );
    executeGdal(['ogr2ogr', '-f', 'GPKG', $stage . '/parishes.gpkg', $stage . '/parishes.geojson',
        '-nln', 'parishes', '-nlt', 'PROMOTE_TO_MULTI', '-t_srs', 'EPSG:4326']);
    executeGdal(['ogr2ogr', '-f', 'ESRI Shapefile', $stage . '/shapefile/parishes.shp',
        $stage . '/parishes.geojson', '-nln', 'parishes', '-nlt', 'PROMOTE_TO_MULTI',
        '-t_srs', 'EPSG:4326', '-lco', 'ENCODING=UTF-8']);
    foreach (['shp', 'shx', 'dbf', 'prj', 'cpg'] as $extension) {
        if (!is_file($stage . '/shapefile/parishes.' . $extension)) {
            throw new RuntimeException('Missing mandatory Shapefile sidecar: ' . $extension);
        }
    }
    foreach (['shapefile/parishes.shp' => 'shp', 'parishes.gpkg' => 'gpkg'] as $source => $type) {
        $roundtrip = $stage . '/verify-' . $type . '.geojson';
        executeGdal(['ogr2ogr', '-f', 'GeoJSON', $roundtrip, $stage . '/' . $source,
            '-t_srs', 'EPSG:4326']);
        $decoded = json_decode((string) file_get_contents($roundtrip), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !is_array($decoded['features'] ?? null)) {
            throw new RuntimeException('Invalid ' . $type . ' roundtrip');
        }
        (new GeometryRoundTripVerifier())->verify($prepared, $decoded);
        unlink($roundtrip);
    }
    file_put_contents($stage . '/fields.json', json_encode($exporter->dictionary(),
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    file_put_contents($stage . '/metadata.json', json_encode([
        'crs' => 'EPSG:4326',
        'feature_count' => count($prepared['features']),
        'source_sha256' => hash_file('sha256', $input),
        'publication_rights' => 'review_required',
        'topology_status' => $data['metadata']['topology_status'] ?? 'not_checked',
        'note' => 'Local export only; do not publish without source rights and topology acceptance',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    if (!rename($stage, $destination)) {
        throw new RuntimeException('Cannot publish complete local export directory');
    }
    fwrite(STDOUT, "All GIS formats round-trip with consistent canonical identities\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    removeStage($stage);
}
