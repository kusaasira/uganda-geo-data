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

            $inspector = new SchemaInspector($this->pdo);
            foreach ($fresh['operations'] as $operation) {
                $kind = $operation['kind'] ?? null;
                if ($kind === 'insert_managed') {
                    $after = $operation['after'] ?? null;
                    if (!is_array($after)) {
                        throw new RuntimeException('Invalid insert operation');
                    }
                    $statement = $this->pdo->prepare('INSERT INTO uganda_geo_units
                        (source_hash, source_key, level, name, parent_hash, snapshot_hash)
                        VALUES (?, ?, ?, ?, ?, ?)');
                    $statement->execute([
                        $after['source_hash'], $after['source_key'], $after['level'],
                        $after['name'], $after['parent_hash'], $after['snapshot_hash'],
                    ]);
                } elseif ($kind === 'update_managed') {
                    $after = $operation['after'] ?? null;
                    $before = $operation['before'] ?? null;
                    if (!is_array($after) || !is_array($before)) {
                        throw new RuntimeException('Invalid managed update operation');
                    }
                    $statement = $this->pdo->prepare("UPDATE uganda_geo_units
                        SET level = ?, name = ?, parent_hash = ?, snapshot_hash = ?
                        WHERE source_hash = ? AND source_key = ? AND level = ? AND name = ?
                        AND COALESCE(parent_hash, '') = ?");
                    $statement->execute([
                        $after['level'], $after['name'], $after['parent_hash'], $after['snapshot_hash'],
                        $before['source_hash'], $before['source_key'], $before['level'], $before['name'],
                        $before['parent_hash'] ?? '',
                    ]);
                    if ($statement->rowCount() !== 1) {
                        throw new RuntimeException('Managed record changed during sync');
                    }
                } elseif ($kind === 'update_existing') {
                    $this->updateExisting($operation, $inspector);
                } else {
                    throw new RuntimeException('Unknown synchronization operation');
                }
            }
            $this->pdo->commit();
            return [
                'status' => 'applied',
                'plan_hash' => $fresh['plan_hash'],
                'operations' => count($fresh['operations']),
                'unchanged' => $fresh['unchanged'],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string, mixed> $operation */
    private function updateExisting(array $operation, SchemaInspector $inspector): void
    {
        $level = $operation['level'] ?? null;
        if (!is_string($level)) {
            throw new RuntimeException('Missing external target level');
        }
        $def = $this->mapping->levels()[$level] ?? null;
        if ($def === null) {
            throw new RuntimeException('Unsupported external target level');
        }
        $table = $inspector->quote($def['table']);
        $key = $inspector->quote($def['key']);
        $name = $inspector->quote($def['name']);
        $values = [];
        $set = [];
        if (($operation['update_name'] ?? false) === true) {
            $set[] = "$name = ?";
            $values[] = $operation['after_name'];
        }
        if (($operation['update_parent'] ?? false) === true) {
            $column = $inspector->quote($def['parent']);
            $set[] = "$column = ?";
            $values[] = $operation['after_parent'];
        }
        $conditions = ["$key = ?"];
        $values[] = $operation['target_key'];
        $this->expectedCondition($conditions, $values, $name, $operation['before_name']);
        if (isset($def['parent'])) {
            $this->expectedCondition($conditions, $values, $inspector->quote($def['parent']), $operation['before_parent']);
        }
        $sql = "UPDATE $table SET " . implode(', ', $set) . ' WHERE ' . implode(' AND ', $conditions);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Local record changed during synchronization');
        }
    }

    /**
     * @param array<int, string> $conditions
     * @param array<int, mixed> $values
     */
    private function expectedCondition(array &$conditions, array &$values, string $column, mixed $expected): void
    {
        if ($expected === null) {
            $conditions[] = "$column IS NULL";
        } else {
            $conditions[] = "$column = ?";
            $values[] = $expected;
        }
    }
}
