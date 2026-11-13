<?php

declare(strict_types=1);

namespace unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Sync\IdentityRegistry;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\SchemaInspector;
use Uganda\Sync\SchemaInstaller;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\SyncPlanner;

final class SyncSqliteIntegrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite is unavailable');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @return array<string, mixed> */
    private function snapshot(string $name = 'Northern'): array
    {
        return [
            'schema_version' => 1,
            'snapshot_id' => 'fictional-integration-fixture',
            'reference_date' => null,
            'source_ids' => ['test-source'],
            'units' => [
                ['id' => 'test:district:1', 'level' => 'district', 'name' => $name,
                    'parent_id' => null, 'source_id' => 'test-source', 'source_record_id' => '1',
                    'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active'],
                ['id' => 'test:county:1', 'level' => 'county', 'name' => 'Central',
                    'parent_id' => 'test:district:1', 'source_id' => 'test-source', 'source_record_id' => '1',
                    'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active'],
                ['id' => 'test:county:2', 'level' => 'county', 'name' => 'Central',
                    'parent_id' => 'test:district:1', 'source_id' => 'test-source', 'source_record_id' => '2',
                    'aliases' => [], 'external_codes' => [], 'lifecycle_status' => 'active'],
            ],
        ];
    }

    #[Test]
    public function doesNotWriteOnDiscoveryOrPlanningAndPreservesDuplicates(): void
    {
        (new SchemaInstaller($this->pdo))->install();
        $mapping = MappingDefinition::managed();
        $planner = new SyncPlanner($this->pdo, $mapping);
        $plan = $planner->plan($this->snapshot());
        self::assertCount(3, $plan['operations']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());

        $result = (new SyncExecutor($this->pdo, $mapping))->apply($this->snapshot(), $plan, $plan['plan_hash']);
        self::assertSame('applied', $result['status']);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM uganda_geo_units WHERE name = 'Central'")->fetchColumn());
        self::assertSame([], $planner->plan($this->snapshot())['operations']);
    }

    #[Test]
    public function refusesStaleApprovedPlanWithoutPartialWrites(): void
    {
        (new SchemaInstaller($this->pdo))->install();
        $mapping = MappingDefinition::managed();
        $snapshot = $this->snapshot();
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        $statement = $this->pdo->prepare('INSERT INTO uganda_geo_units
            (source_hash, source_key, level, name, parent_hash, snapshot_hash) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([hash('sha256', 'test:district:1'), 'test:district:1', 'district', 'Manual', null, 'other']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Stale sync plan');
        (new SyncExecutor($this->pdo, $mapping))->apply($snapshot, $plan, $plan['plan_hash']);
    }

    #[Test]
    public function rejectsReplayedApprovalAgainstDifferentSourceSnapshot(): void
    {
        (new SchemaInstaller($this->pdo))->install();
        $mapping = MappingDefinition::managed();
        $snapshot = $this->snapshot();
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        $executor = new SyncExecutor($this->pdo, $mapping);
        $executor->apply($snapshot, $plan, $plan['plan_hash']);

        $changed = $this->snapshot('Different Source Name');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('different source or target');
        $executor->apply($changed, $plan, $plan['plan_hash']);
    }

    #[Test]
    public function explicitlyMappedExistingIdsSurviveNameUpdates(): void
    {
        $this->pdo->exec('CREATE TABLE geo_districts (uuid TEXT PRIMARY KEY, district_name TEXT NOT NULL, created_by TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO geo_districts VALUES ('local-uuid-1', 'Previous', 'owner')");
        (new SchemaInstaller($this->pdo))->install();
        $mapping = new MappingDefinition(['strategy' => 'existing', 'levels' => [
            'district' => ['table' => 'geo_districts', 'key' => 'uuid', 'name' => 'district_name', 'write_name' => true],
        ]]);
        (new IdentityRegistry($this->pdo, $mapping))->approve('test:district:1', 'district', 'local-uuid-1', 'reviewer');
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($this->snapshot());
        self::assertSame(1, count($plan['operations']));
        self::assertSame(0, $plan['unmapped']);
        (new SyncExecutor($this->pdo, $mapping))->apply($this->snapshot(), $plan, $plan['plan_hash']);
        $row = $this->pdo->query("SELECT * FROM geo_districts WHERE uuid = 'local-uuid-1'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('Northern', $row['district_name']);
        self::assertSame('owner', $row['created_by']);
    }

    #[Test]
    public function reportsMissingSourceUnitsWithoutRetiringOrDeletingLocalRows(): void
    {
        (new SchemaInstaller($this->pdo))->install();
        $mapping = MappingDefinition::managed();
        $snapshot = $this->snapshot();
        $original = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        (new SyncExecutor($this->pdo, $mapping))->apply($snapshot, $original, $original['plan_hash']);

        array_pop($snapshot['units']);
        $updated = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        self::assertSame([], $updated['operations']);
        self::assertSame([
            ['level' => 'county', 'source_key' => 'test:county:2'],
        ], $updated['missing_from_source']);

        (new SyncExecutor($this->pdo, $mapping))->apply($snapshot, $updated, $updated['plan_hash']);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());
    }

    #[Test]
    public function discoversCandidatesButNeverAssumesTheyAreConfirmed(): void
    {
        $this->pdo->exec('CREATE TABLE geo_districts (uuid TEXT PRIMARY KEY, district_name TEXT)');
        $candidates = (new SchemaInspector($this->pdo))->candidates();
        self::assertSame('geo_districts', $candidates['district'][0]['table']);
        self::assertContains('district_name', $candidates['district'][0]['columns']);
    }
}
