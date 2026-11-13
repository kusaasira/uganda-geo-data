<?php

declare(strict_types=1);

namespace unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Sync\MappingDefinition;
use Uganda\Sync\ResumableSyncExecutor;
use Uganda\Sync\SchemaInstaller;
use Uganda\Sync\SyncPlanner;
use Uganda\Sync\SyncExecutor;
use Uganda\Sync\SyncLease;

final class ResumableSyncExecutorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite is unavailable');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaInstaller($this->pdo))->install();
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        $units = [];
        foreach (['district', 'county', 'subcounty'] as $position => $level) {
            $units[] = [
                'id' => 'test:' . $level . ':1',
                'level' => $level,
                'name' => 'Fixture ' . $level,
                'parent_id' => $position === 0 ? null : 'test:' . ['district', 'county'][$position - 1] . ':1',
                'source_id' => 'test-source',
                'source_record_id' => '1',
                'aliases' => [],
                'external_codes' => [],
                'lifecycle_status' => 'active',
            ];
        }
        return ['schema_version' => 1, 'snapshot_id' => 'fictional-checkpoint-fixture',
            'source_ids' => ['test-source'], 'reference_date' => null, 'units' => $units];
    }

    #[Test]
    public function appliesOneCommittedBatchAtATimeAndResumesAfterRestart(): void
    {
        $mapping = MappingDefinition::managed();
        $snapshot = $this->fixture();
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        $executor = new ResumableSyncExecutor($this->pdo, $mapping);

        $first = $executor->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
        self::assertSame('running', $first['status']);
        self::assertSame(1, $first['next_index']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());

        $resumed = new ResumableSyncExecutor($this->pdo, $mapping);
        $second = $resumed->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
        self::assertSame(2, $second['next_index']);
        self::assertSame('running', $second['status']);

        $last = $resumed->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
        self::assertSame('completed', $last['status']);
        self::assertSame(3, $last['next_index']);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_sync_runs')->fetchColumn());

        $again = $executor->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
        self::assertSame('already_applied', $again['status']);
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_units')->fetchColumn());
    }

    #[Test]
    public function rejectsChangedApprovedPlanAfterAnAlreadyCommittedBatch(): void
    {
        $mapping = MappingDefinition::managed();
        $snapshot = $this->fixture();
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        $executor = new ResumableSyncExecutor($this->pdo, $mapping);
        $executor->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
        $snapshot['units'][2]['name'] = 'Changed while running';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match pinned source');
        $executor->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
    }

    #[Test]
    public function rollsBackFailedBatchWhileRetainingPreviouslyCommittedCheckpoint(): void
    {
        $mapping = MappingDefinition::managed();
        $snapshot = $this->fixture();
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        $executor = new ResumableSyncExecutor($this->pdo, $mapping);
        $executor->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
        $statement = $this->pdo->prepare('INSERT INTO uganda_geo_units
            (source_hash, source_key, level, name, parent_hash, snapshot_hash)
            VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([hash('sha256', 'test:county:1'), 'test:county:1',
            'county', 'Concurrent Edit', null, 'concurrent']);

        try {
            $executor->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
            self::fail('Concurrent conflicting key must fail');
        } catch (\Throwable $exception) {
            self::assertNotSame('', $exception->getMessage());
        }
        $checkpoint = $this->pdo->query('SELECT next_index, status FROM uganda_geo_sync_checkpoints')
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame(1, (int) $checkpoint['next_index']);
        self::assertSame('running', $checkpoint['status']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM uganda_geo_sync_runs')->fetchColumn());
    }

    #[Test]
    public function reservesTheDatabaseAcrossBatchesAndBlocksOtherApprovedPlans(): void
    {
        $mapping = MappingDefinition::managed();
        $snapshot = $this->fixture();
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        $runner = new ResumableSyncExecutor($this->pdo, $mapping);
        $first = $runner->applyNext($snapshot, $plan, $plan['plan_hash'], 1);
        self::assertSame('running', $first['status']);
        self::assertSame($plan['plan_hash'], (new SyncLease($this->pdo))->active()['plan_hash']);

        $competing = $this->fixture();
        $competing['units'][2]['name'] = 'Competing source value';
        $otherPlan = (new SyncPlanner($this->pdo, $mapping))->plan($competing);
        try {
            (new SyncExecutor($this->pdo, $mapping))->apply($competing, $otherPlan, $otherPlan['plan_hash']);
            self::fail('Concurrent plan must not obtain an active database lease');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('owns this database', $exception->getMessage());
        }
        self::assertSame('completed', $runner->applyAll($snapshot, $plan, $plan['plan_hash'], 1)['status']);
        self::assertNull((new SyncLease($this->pdo))->active());

        $nextPlan = (new SyncPlanner($this->pdo, $mapping))->plan($competing);
        self::assertSame('applied',
            (new SyncExecutor($this->pdo, $mapping))->apply($competing, $nextPlan, $nextPlan['plan_hash'])['status']);
    }

    #[Test]
    public function disallowsInvalidBatchSizes(): void
    {
        $mapping = MappingDefinition::managed();
        $snapshot = $this->fixture();
        $plan = (new SyncPlanner($this->pdo, $mapping))->plan($snapshot);
        $this->expectException(\InvalidArgumentException::class);
        (new ResumableSyncExecutor($this->pdo, $mapping))->applyNext($snapshot, $plan, $plan['plan_hash'], 1001);
    }
}
