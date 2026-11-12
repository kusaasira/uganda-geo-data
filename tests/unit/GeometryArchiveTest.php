<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\GeometryArchive;

final class GeometryArchiveTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itPackagesExplicitFilesWithoutPublicationApproval(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('Optional PHP zip extension unavailable');
        }
        $root = sys_get_temp_dir() . '/uganda-geo-zip-' . bin2hex(random_bytes(5));
        mkdir($root, 0700);
        mkdir($root . '/shapefile', 0700);
        $files = ['parishes.geojson', 'parishes.gpkg', 'shapefile/parishes.shp',
            'shapefile/parishes.shx', 'shapefile/parishes.dbf',
            'shapefile/parishes.prj', 'shapefile/parishes.cpg', 'fields.json'];
        $zip = $root . '.zip';
        try {
            foreach ($files as $file) {
                file_put_contents($root . '/' . $file, 'test bytes for ' . $file);
            }
            file_put_contents($root . '/metadata.json', json_encode([
                'feature_count' => 1, 'publication_rights' => 'review_required',
                'crs' => 'EPSG:4326',
                'source_sha256' => str_repeat('a', 64),
            ], JSON_THROW_ON_ERROR));
            $manifest = (new GeometryArchive())->build($root, 'v2.0.0-rc.1', $zip);
            self::assertFalse($manifest['release_asset_ready']);
            self::assertSame('review_required', $manifest['publication_rights']);
            self::assertFileExists($zip);
            self::assertCount(9, $manifest['artifacts']);
        } finally {
            if (is_file($zip)) {
                unlink($zip);
            }
            foreach (array_merge($files, ['metadata.json']) as $file) {
                if (is_file($root . '/' . $file)) {
                    unlink($root . '/' . $file);
                }
            }
            rmdir($root . '/shapefile');
            rmdir($root);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsUnlistedReleaseVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GeometryArchive())->build('/invalid', 'v1.0.0', '/tmp/example.zip');
    }
}
