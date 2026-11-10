<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class GeometryArchive
{
    private const FILES = [
        'parishes.geojson',
        'parishes.gpkg',
        'shapefile/parishes.shp',
        'shapefile/parishes.shx',
        'shapefile/parishes.dbf',
        'shapefile/parishes.prj',
        'shapefile/parishes.cpg',
        'fields.json',
        'metadata.json',
    ];

    /** @return array<string, mixed> */
    public function build(string $root, string $version, string $destination): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The optional PHP ext-zip extension is required for local GIS archive packaging');
        }
        if (!preg_match('/^v2\.0\.0(?:-(?:alpha|beta|rc)\.[1-9][0-9]*)?$/D', $version)) {
            throw new InvalidArgumentException('Invalid v2 geometry archive version');
        }
        if (file_exists($destination) || is_link($destination) || !is_dir(dirname($destination))) {
            throw new InvalidArgumentException('Archive destination must be unused');
        }
        $manifest = (new ArtifactManifest())->build($root, self::FILES);
        $metadataPath = rtrim($root, '/') . '/metadata.json';
        $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($metadata) || !is_int($metadata['feature_count'] ?? null)
            || $metadata['feature_count'] <= 0
            || !in_array($metadata['publication_rights'] ?? null, ['permitted', 'review_required'], true)
            || !is_string($metadata['source_sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $metadata['source_sha256'])
            || ($metadata['crs'] ?? null) !== 'EPSG:4326') {
            throw new InvalidArgumentException('Invalid GIS distribution metadata');
        }
        $manifest['package_version'] = $version;
        $manifest['publication_rights'] = $metadata['publication_rights'];
        $manifest['release_asset_ready'] = false;
        $manifest['note'] = 'Local review bundle; publication requires independent source, topology and coverage approval';
        $temp = $destination . '.partial-' . bin2hex(random_bytes(6));
        $archive = new ZipArchive();
        try {
            if ($archive->open($temp, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new RuntimeException('Cannot create temporary GIS archive');
            }
            foreach (self::FILES as $file) {
                $path = rtrim($root, '/') . '/' . $file;
                if (is_link($path) || !$archive->addFile($path, $file)) {
                    throw new RuntimeException('Cannot add release file: ' . $file);
                }
                if (!$archive->setMtimeName($file, 315532800)) {
                    throw new RuntimeException('Unable to normalize archive file timestamp: ' . $file);
                }
            }
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
            if (!$archive->addFromString('manifest.json', $json)) {
                throw new RuntimeException('Cannot add archive manifest');
            }
            if (!$archive->setMtimeName('manifest.json', 315532800)) {
                throw new RuntimeException('Unable to normalize archive manifest timestamp');
            }
            if (!$archive->close()) {
                throw new RuntimeException('Could not complete GIS archive');
            }
            $check = new ZipArchive();
            if ($check->open($temp) !== true) {
                throw new RuntimeException('Unable to reopen GIS archive for validation');
            }
            try {
                $items = $manifest['artifacts'] ?? null;
                if (!is_array($items)) {
                    throw new RuntimeException('Malformed archive artifact manifest');
                }
                foreach ($items as $item) {
                    if (!is_array($item) || !is_string($item['path'] ?? null)
                        || !is_string($item['sha256'] ?? null)
                        || !is_int($item['bytes'] ?? null)) {
                        throw new RuntimeException('Malformed archive artifact entry');
                    }
                    $raw = $check->getFromName($item['path']);
                    if (!is_string($raw) || strlen($raw) !== $item['bytes']
                        || hash('sha256', $raw) !== $item['sha256']) {
                        throw new RuntimeException('GIS archive checksum mismatch: ' . $item['path']);
                    }
                }
                if ($check->numFiles !== count(self::FILES) + 1) {
                    throw new RuntimeException('GIS archive contains unexpected files');
                }
            } finally {
                $check->close();
            }
            if (file_exists($destination) || !rename($temp, $destination)) {
                throw new RuntimeException('Unable to publish complete local GIS review bundle');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
        return $manifest;
    }
}
