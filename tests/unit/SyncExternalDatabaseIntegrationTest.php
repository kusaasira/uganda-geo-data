<?php

declare(strict_types=1);

namespace unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\ResumableSyncExecutor;
use Uganda\Sync\SchemaInspector;
use Uganda\Sync\SchemaInstaller;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\SyncPlanner;

final class SyncExternalDatabaseIntegrationTest extends TestCase
{
    /**
     * This test may perform schema DDL. It is disabled unless explicitly enabled
     * for a disposable CI database.
     */
    #[Test]
    public function executesIdenticalVerifiedSyncOnSupportedDatabaseAdapters(): void
    {
        if (getenv('UGANDA_GEO_TEST_ALLOW_SCHEMA_RESET') !== '1') {
            self::markTestSkipped('Requires explicitly authorized disposable test database');
        }
        $dsn = getenv('UGANDA_GEO_TEST_DSN');
        if (!$dsn) {
            self::markTestSkipped('No isolated integration-test DSN');
        }
        $pdo = new PDO($dsn, getenv('UGANDA_GEO_TEST_USER') ?: null, getenv('UGANDA_GEO_TEST_PASSWORD') ?: null);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::assertContains($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ['mysql', 'pgsql']);

        foreach (['uganda_geo_sync_runs', 'uganda_geo_identity_maps', 'uganda_geo_units'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }

        try {
            (new SchemaInstaller($pdo))->install();
            $mapping = MappingDefinition::managed();
            (new SchemaInspector($pdo))->assertMappedColumns($mapping);
            $snapshot = [
                'schema_version' => 1,
                'snapshot_id' => 'fictional-ci-database-fixture',
                'source_ids' => ['test-source'],
                'reference_date' => null,
                'units' => [
                    ['id' => 'test:district:1', 'level' => 'district', 'name' => 'Fixture A',
                        'parent_id' => null, 'source_id' => 'test-source', 'source_record_id' => '1',
                        'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active'],
                    ['id' => 'test:county:1', 'level' => 'county', 'name' => 'Repeated',
                        'parent_id' => 'test:district:1', 'source_id' => 'test-source', 'source_record_id' => '1',
                        'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active'],
                    ['id' => 'test:county:2', 'level' => 'county', 'name' => 'Repeated',
                        'parent_id' => 'test:district:1', 'source_id' => 'test-source', 'source_record_id' => '2',
                        'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active'],
                ],
            ];
            $planner = new SyncPlanner($pdo, $mapping);
            $plan = $planner->plan($snapshot);
            self::assertCount(3, $plan['operations']);
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());

            $executor = new SyncExecutor($pdo, $mapping);
            $result = $executor->apply($snapshot, $plan, $plan['plan_hash']);
            self::assertSame('applied', $result['status']);
            self::assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());

            $again = $executor->apply($snapshot, $plan, $plan['plan_hash']);
            self::assertSame('already_applied', $again['status']);
            self::assertSame([], $planner->plan($snapshot)['operations']);

            $snapshot['units'][0]['name'] = 'Fixture A Revised';
            $snapshot['units'][1]['name'] = 'Renamed';
            $changed = $planner->plan($snapshot);
            self::assertCount(2, $changed['operations']);
            $batchRunner = new ResumableSyncExecutor($pdo, $mapping);
            $firstBatch = $batchRunner->applyNext($snapshot, $changed, $changed['plan_hash'], 1);
            self::assertSame('running', $firstBatch['status']);
            self::assertSame(1, $firstBatch['next_index']);
            $lastBatch = (new ResumableSyncExecutor($pdo, $mapping))
                ->applyNext($snapshot, $changed, $changed['plan_hash'], 1);
            self::assertSame('completed', $lastBatch['status']);
            self::assertSame(2, $lastBatch['next_index']);
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM uganda_geo_units WHERE name = 'Renamed'")->fetchColumn());
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM uganda_geo_units WHERE name = 'Repeated'")->fetchColumn());
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM uganda_geo_units WHERE name = 'Fixture A Revised'")->fetchColumn());
        } finally {
            foreach (['uganda_geo_sync_runs', 'uganda_geo_identity_maps', 'uganda_geo_units'] as $table) {
                $pdo->exec('DROP TABLE IF EXISTS ' . $table);
            }
        }
    }
}
