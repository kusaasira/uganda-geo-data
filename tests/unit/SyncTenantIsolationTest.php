<?php

declare(strict_types=1);

namespace unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Sync\IdentityRegistry;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\SchemaInstaller;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\SyncPlanner;

final class SyncTenantIsolationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite is unavailable');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE district_locations (
            local_uuid TEXT PRIMARY KEY,
            tenant_id TEXT NOT NULL,
            name TEXT NOT NULL,
            created_by TEXT NOT NULL
        )');
        $this->pdo->exec("INSERT INTO district_locations VALUES
            ('tenant-one-record', 'tenant-one', 'Previous', 'alice'),
            ('tenant-two-record', 'tenant-two', 'Previous', 'bob')");
        (new SchemaInstaller($this->pdo))->install();
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'schema_version' => 1,
            'snapshot_id' => 'fictional-tenant-isolation',
            'source_ids' => ['fixture'],
            'units' => [
                [
                    'id' => 'test:district:1',
                    'level' => 'district',
                    'name' => 'New District Name',
                    'parent_id' => null,
                    'source_id' => 'fixture',
                    'source_record_id' => '1',
                    'aliases' => [],
                    'lifecycle_status' => 'active',
                    'external_codes' => [],
                ],
            ],
        ];
    }

    #[Test]
    public function rejectsCrossTenantMappingAndOnlyUpdatesApprovedTenant(): void
    {
        $mapping = new MappingDefinition([
            'strategy' => 'existing',
            'levels' => [
                'district' => [
                    'table' => 'district_locations',
                    'key' => 'local_uuid',
                    'name' => 'name',
                    'scope_column' => 'tenant_id',
                    'write_name' => true,
                ],
            ],
        ]);
        $registry = new IdentityRegistry($this->pdo, $mapping, 'tenant-one');
        try {
            $registry->approve('test:district:1', 'district', 'tenant-two-record', 'reviewer');
            self::fail('Cross-tenant mapping must be rejected');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('exactly one existing target', $exception->getMessage());
        }

        $registry->approve('test:district:1', 'district', 'tenant-one-record', 'reviewer');
        $plan = (new SyncPlanner($this->pdo, $mapping, 'tenant-one'))->plan($this->snapshot());
        self::assertCount(1, $plan['operations']);
        (new SyncExecutor($this->pdo, $mapping, 'tenant-one'))->apply($this->snapshot(), $plan, $plan['plan_hash']);

        $rows = $this->pdo->query('SELECT local_uuid, tenant_id, name, created_by
            FROM district_locations ORDER BY local_uuid')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame('New District Name', $rows[0]['name']);
        self::assertSame('alice', $rows[0]['created_by']);
        self::assertSame('Previous', $rows[1]['name']);
        self::assertSame('bob', $rows[1]['created_by']);
    }
}
