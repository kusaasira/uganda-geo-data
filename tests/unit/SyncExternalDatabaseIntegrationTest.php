<?php

declare(strict_types=1);

namespace unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Sync\IdentityRegistry;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\ResumableSyncExecutor;
use Uganda\Sync\SchemaInspector;
use Uganda\Sync\SchemaInstaller;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\SyncOperationWriter;
use Uganda\Sync\SyncPlanner;

final class SyncExternalDatabaseIntegrationTest extends TestCase
{
    #[Test]
    public function rejectsCaseInsensitiveMySqlCollationMatchesDuringIdentityApprovalAndStaleWrites(): void
    {
        if (getenv('UGANDA_GEO_TEST_ALLOW_SCHEMA_RESET') !== '1') {
            self::markTestSkipped('Requires explicitly authorized disposable database');
        }
        $dsn = getenv('UGANDA_GEO_TEST_DSN');
        if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
            self::markTestSkipped('Regression targets MySQL case-insensitive collations');
        }
        $pdo = new PDO(
            $dsn,
            getenv('UGANDA_GEO_TEST_USER') ?: null,
            getenv('UGANDA_GEO_TEST_PASSWORD') ?: null
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('DROP TABLE IF EXISTS uganda_geo_case_fixture');
        $pdo->exec('CREATE TABLE uganda_geo_case_fixture (
            uuid VARCHAR(64) NOT NULL PRIMARY KEY,
            name VARCHAR(150) COLLATE utf8mb4_0900_ai_ci NOT NULL,
            tenant_id VARCHAR(64) COLLATE utf8mb4_0900_ai_ci NOT NULL
        ) ENGINE=InnoDB');
        try {
            $pdo->exec("INSERT INTO uganda_geo_case_fixture VALUES ('local-1', 'Previous', 'tenant-one')");
            (new SchemaInstaller($pdo))->install();
            $mapping = new MappingDefinition([
                'strategy' => 'existing',
                'levels' => [
                    'district' => [
                        'table' => 'uganda_geo_case_fixture', 'key' => 'uuid',
                        'name' => 'name', 'scope_column' => 'tenant_id', 'write_name' => true,
                    ],
                ],
            ]);
            $registry = new IdentityRegistry($pdo, $mapping, 'tenant-one');
            $registry->approve('test:district:1', 'district', 'local-1', 'reviewer');
            $snapshot = [
                'schema_version' => 1, 'snapshot_id' => 'fictional-mysql-collation-test',
                'source_ids' => ['fixture'], 'units' => [
                    ['id' => 'test:district:1', 'level' => 'district', 'name' => 'Updated',
                        'parent_id' => null, 'source_id' => 'fixture', 'source_record_id' => '1',
                        'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active'],
                ],
            ];
            $plan = (new SyncPlanner($pdo, $mapping, 'tenant-one'))->plan($snapshot);
            self::assertCount(1, $plan['operations']);

            $pdo->exec("UPDATE uganda_geo_case_fixture SET name = 'previous' WHERE uuid = 'local-1'");
            $pdo->beginTransaction();
            try {
                (new SyncOperationWriter($pdo, $mapping, 'tenant-one'))->apply($plan['operations'][0]);
                self::fail('A case-only concurrent change must invalidate optimistic updates');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('changed during synchronization', $exception->getMessage());
            } finally {
                $pdo->rollBack();
            }
            self::assertSame('previous', $pdo->query(
                "SELECT name FROM uganda_geo_case_fixture WHERE uuid = 'local-1'"
            )->fetchColumn());

            $pdo->exec("UPDATE uganda_geo_case_fixture SET tenant_id = 'TENANT-ONE' WHERE uuid = 'local-1'");
            try {
                $registry->approve('test:district:2', 'district', 'local-1', 'reviewer');
                self::fail('Collation-equivalent tenant IDs must not be treated as identical');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('different exact tenant scope', $exception->getMessage());
            }
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS uganda_geo_case_fixture');
        }
    }

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

        foreach (['uganda_geo_sync_leases', 'uganda_geo_sync_checkpoints',
            'uganda_geo_sync_runs', 'uganda_geo_identity_maps', 'uganda_geo_units'] as $table) {
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
            foreach (['uganda_geo_sync_leases', 'uganda_geo_sync_checkpoints',
            'uganda_geo_sync_runs', 'uganda_geo_identity_maps', 'uganda_geo_units'] as $table) {
                $pdo->exec('DROP TABLE IF EXISTS ' . $table);
            }
        }
    }
}
