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

final class SyncHybridIntegrationTest extends TestCase
{
    #[Test]
    public function mirrorsPackagedSourceWhileKeepingExternalRecordAndUuidUntouched(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite is unavailable');
        }
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE partner_districts (
            uuid TEXT PRIMARY KEY, tenant_id TEXT NOT NULL,
            district_label TEXT NOT NULL, created_by TEXT NOT NULL
        )');
        $pdo->exec("INSERT INTO partner_districts VALUES
            ('uuid-0101', 'organization-1', 'Existing Locality', 'original-owner')");
        (new SchemaInstaller($pdo))->install();

        $mapping = new MappingDefinition([
            'strategy' => 'hybrid',
            'levels' => MappingDefinition::managed()->levels(),
            'external_levels' => [
                'district' => [
                    'table' => 'partner_districts',
                    'key' => 'uuid',
                    'name' => 'district_label',
                    'scope_column' => 'tenant_id',
                ],
            ],
        ]);
        $registry = new IdentityRegistry($pdo, $mapping, 'organization-1');
        $registry->approve('test:district:7', 'district', 'uuid-0101', 'steward');
        self::assertSame('uuid-0101', $registry->mappedKey('test:district:7', 'district'));

        $snapshot = [
            'schema_version' => 1, 'snapshot_id' => 'fictional-hybrid-test',
            'source_ids' => ['fixture'], 'units' => [
                [
                    'id' => 'test:district:7', 'level' => 'district', 'name' => 'Canonical Locality',
                    'parent_id' => null, 'source_id' => 'fixture', 'source_record_id' => '7',
                    'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active',
                ],
            ],
        ];
        $plan = (new SyncPlanner($pdo, $mapping, 'organization-1'))->plan($snapshot);
        self::assertSame('hybrid', $plan['strategy']);
        self::assertCount(1, $plan['operations']);
        (new SyncExecutor($pdo, $mapping, 'organization-1'))->apply($snapshot, $plan, $plan['plan_hash']);

        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());
        $row = $pdo->query("SELECT * FROM partner_districts WHERE uuid = 'uuid-0101'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Existing Locality', $row['district_label']);
        self::assertSame('original-owner', $row['created_by']);

        $pdo->exec('DROP TABLE partner_districts');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing mapped table');
        (new SyncPlanner($pdo, $mapping, 'organization-1'))->plan($snapshot);
    }
}
