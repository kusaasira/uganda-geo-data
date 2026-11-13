<?php

declare(strict_types=1);

namespace Uganda\Sync;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class SyncExecutor
{
    public function __construct(
        private PDO $pdo,
        private MappingDefinition $mapping,
        private string $scope = 'default',
    ) {
    }

    /**
     * Apply exactly one independently approved immutable plan in a single transaction.
     * Any optimistic concurrency failure rolls back the entire run.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $approvedPlan
     * @return array<string, mixed>
     */
    public function apply(array $snapshot, array $approvedPlan, string $approvedPlanHash): array
    {
        if (!isset($approvedPlan['plan_hash']) ||
            !is_string($approvedPlan['plan_hash']) ||
            !hash_equals($approvedPlan['plan_hash'], $approvedPlanHash)) {
            throw new InvalidArgumentException('Explicit approval must match the complete immutable plan hash');
        }
        $catalog = new SnapshotCatalog();
        $snapshotHash = $catalog->digest($snapshot);
        if (($approvedPlan['snapshot_hash'] ?? null) !== $snapshotHash ||
            ($approvedPlan['mapping_hash'] ?? null) !== $this->mapping->fingerprint() ||
            ($approvedPlan['scope'] ?? null) !== $this->scope ||
            ($approvedPlan['strategy'] ?? null) !== $this->mapping->strategy()) {
            throw new InvalidArgumentException('Approved sync plan belongs to a different source or target configuration');
        }
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Synchronization owns its transaction boundary');
        }

        $this->pdo->beginTransaction();
        try {
            $prior = $this->pdo->prepare('SELECT plan_hash FROM uganda_geo_sync_runs WHERE plan_hash = ?');
            $prior->execute([$approvedPlanHash]);
            if ($prior->fetchColumn() !== false) {
                $this->pdo->commit();
                return ['status' => 'already_applied', 'plan_hash' => $approvedPlanHash, 'operations' => 0];
            }
            $fresh = (new SyncPlanner($this->pdo, $this->mapping, $this->scope))->plan($snapshot);
            if (!hash_equals($approvedPlanHash, $fresh['plan_hash'])) {
                throw new RuntimeException('Stale sync plan: source, mapping or database state changed');
            }
            if ($fresh['conflicts'] !== [] || ($fresh['unmapped'] > 0 && $fresh['strategy'] === 'existing' && $fresh['unmapped_policy'] !== 'ignore')) {
                throw new RuntimeException('Unresolved existing-database identity mappings or conflicts');
            }

            $statement = $this->pdo->prepare('INSERT INTO uganda_geo_sync_runs
                (plan_hash, mapping_scope, snapshot_hash, mapping_hash, applied_at, operation_count) VALUES (?, ?, ?, ?, ?, ?)');
            $statement->execute([
                $fresh['plan_hash'], $this->scope, $fresh['snapshot_hash'], $fresh['mapping_hash'],
                gmdate('Y-m-d\TH:i:s\Z'), count($fresh['operations']),
            ]);

            $writer = new SyncOperationWriter($this->pdo, $this->mapping, $this->scope);
            foreach ($fresh['operations'] as $operation) {
                $writer->apply($operation);
            }
            $this->pdo->commit();
            return [
                'status' => 'applied',
                'plan_hash' => $fresh['plan_hash'],
                'operations' => count($fresh['operations']),
                'unchanged' => $fresh['unchanged'],
            ];
        } catch (Throwable $exception) {
            $this->rollbackIfActive();
            throw $exception;
        }
    }

    private function rollbackIfActive(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
