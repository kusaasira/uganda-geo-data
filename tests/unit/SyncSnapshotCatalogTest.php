<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Sync\SnapshotCatalog;
use Uganda\Sync\SnapshotExporter;

final class SyncSnapshotCatalogTest extends TestCase
{
    #[Test]
    public function hasReproducibleIdentityManifestWithoutClaimingSourceCurrency(): void
    {
        $catalog = new SnapshotCatalog();
        $snapshot = $catalog->bundled();
        $manifest = $catalog->manifest($snapshot);
        self::assertSame('legacy-bundled-baseline', $manifest['snapshot_id']);
        self::assertNull($manifest['reference_date']);
        self::assertSame(count($snapshot['units']), $manifest['total_units']);
        self::assertSame($catalog->digest($snapshot), $manifest['sha256']);
    }

    #[Test]
    public function refusesInvalidCanonicalParents(): void
    {
        $snapshot = (new SnapshotCatalog())->bundled();
        $snapshot['units'][1]['parent_id'] = 'does-not-exist';
        $this->expectException(InvalidArgumentException::class);
        (new SnapshotCatalog())->validate($snapshot);
    }

    #[Test]
    public function exportsWithoutOverwritingAndPreservesJsonSourceIdentity(): void
    {
        $catalog = new SnapshotCatalog();
        $snapshot = $catalog->bundled();
        $path = sys_get_temp_dir() . '/uganda-geo-test-' . bin2hex(random_bytes(8)) . '.json';
        try {
            $report = (new SnapshotExporter())->export($snapshot, 'json', $path);
            self::assertSame(hash_file('sha256', $path), $report['sha256']);
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($snapshot['units'][0]['id'], $decoded['units'][0]['id']);
            $this->expectException(\RuntimeException::class);
            (new SnapshotExporter())->export($snapshot, 'json', $path);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    #[Test]
    public function escapesCsvFormulaCellsWithoutChangingCanonicalJsonValues(): void
    {
        $snapshot = (new SnapshotCatalog())->bundled();
        $snapshot['snapshot_id'] = '=SUM(1,2)';
        $snapshot['units'][0]['name'] = ' =HYPERLINK("https://example.invalid")';
        $path = sys_get_temp_dir() . '/uganda-geo-csv-' . bin2hex(random_bytes(8)) . '.csv';
        try {
            (new SnapshotExporter())->export($snapshot, 'csv', $path);
            $handle = fopen($path, 'r');
            self::assertNotFalse($handle);
            try {
                self::assertIsArray(fgetcsv($handle));
                $first = fgetcsv($handle);
                self::assertIsArray($first);
                self::assertSame("'=SUM(1,2)", $first[0]);
                self::assertSame("' =HYPERLINK(\"https://example.invalid\")", $first[3]);
            } finally {
                fclose($handle);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    #[Test]
    public function rejectsUnsupportedExportFormats(): void
    {
        $snapshot = (new SnapshotCatalog())->bundled();
        $this->expectException(InvalidArgumentException::class);
        (new SnapshotExporter())->export($snapshot, 'xml', 'unused.xml');
    }
}
