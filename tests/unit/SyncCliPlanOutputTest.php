<?php

declare(strict_types=1);

namespace unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SyncCliPlanOutputTest extends TestCase
{
    #[Test]
    public function savedPlansKeepApplicationRecordsOutOfCliStandardOutput(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite is unavailable');
        }
        $directory = sys_get_temp_dir() . '/uganda-geo-cli-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $sourcePath = $directory . '/snapshot.json';
        $planPath = $directory . '/plan.json';
        $dbPath = $directory . '/target.sqlite';
        $tool = dirname(__DIR__, 2) . '/tools/sync/uganda-geo.php';
        $snapshot = [
            'schema_version' => 1,
            'snapshot_id' => 'fictional-cli-output-regression',
            'source_ids' => ['test-source'],
            'units' => [
                ['id' => 'test:district:1', 'level' => 'district',
                    'name' => 'Confidential Fixture Label',
                    'parent_id' => null, 'source_id' => 'test-source',
                    'source_record_id' => '1', 'aliases' => [],
                    'external_codes' => [], 'lifecycle_status' => 'active'],
            ],
        ];
        file_put_contents($sourcePath, json_encode($snapshot, JSON_THROW_ON_ERROR));
        $originalDsn = getenv('UGANDA_GEO_DSN');
        putenv('UGANDA_GEO_DSN=sqlite:' . $dbPath);

        try {
            $baseCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tool);
            $installOutput = [];
            $installStatus = 0;
            exec($baseCommand . ' install 2>&1', $installOutput, $installStatus);
            self::assertSame(0, $installStatus, implode("\n", $installOutput));

            $output = [];
            $status = 0;
            exec($baseCommand . ' plan '
                . escapeshellarg('--snapshot=' . $sourcePath) . ' '
                . escapeshellarg('--output=' . $planPath) . ' 2>&1', $output, $status);
            self::assertSame(0, $status, implode("\n", $output));

            $summary = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('planned', $summary['status']);
            self::assertSame(1, $summary['operations']);
            self::assertArrayNotHasKey('operations_detail', $summary);
            self::assertStringNotContainsString('Confidential Fixture Label', implode("\n", $output));
            self::assertStringNotContainsString('test:district:1', implode("\n", $output));

            $private = json_decode((string) file_get_contents($planPath), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('Confidential Fixture Label', $private['operations'][0]['after']['name']);
            self::assertSame(0, fileperms($planPath) & 0077);
        } finally {
            if ($originalDsn === false) {
                putenv('UGANDA_GEO_DSN');
            } else {
                putenv('UGANDA_GEO_DSN=' . $originalDsn);
            }
            foreach ([$sourcePath, $planPath, $dbPath] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }
}
