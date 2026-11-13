<?php

declare(strict_types=1);

namespace Uganda\Sync;

use PDO;
use RuntimeException;

final class SyncOperationWriter
{
    public function __construct(
        private PDO $pdo,
        private MappingDefinition $mapping,
        private string $scope = 'default',
    ) {
    }

    /**
     * Apply exactly one prevalidated operation inside the caller's transaction.
     *
     * @param array<string, mixed> $operation
     */
    public function apply(array $operation): void
    {
        $inspector = new SchemaInspector($this->pdo);
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
            $nameMatch = $this->strictTextMatch('name');
            $statement = $this->pdo->prepare("UPDATE uganda_geo_units
                SET level = ?, name = ?, parent_hash = ?, snapshot_hash = ?
                WHERE source_hash = ? AND source_key = ? AND level = ? AND $nameMatch
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
            if (!isset($def['parent'])) {
                throw new RuntimeException('Parent update requires an explicit mapped parent column');
            }
            $column = $inspector->quote($def['parent']);
            $set[] = "$column = ?";
            $values[] = $operation['after_parent'];
        }
        $conditions = ["$key = ?"];
        $values[] = $operation['target_key'];
        if (isset($def['scope_column'])) {
            $scope = $inspector->quote($def['scope_column']);
            $conditions[] = $this->strictTextMatch($scope);
            $values[] = $this->scope;
        }
        $this->expectedCondition($conditions, $values, $name, $operation['before_name'], true);
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

    private function strictTextMatch(string $column): string
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            return "CAST($column AS BINARY) = CAST(? AS BINARY)";
        }
        if ($driver === 'sqlite') {
            return "$column COLLATE BINARY = ?";
        }
        if ($driver === 'pgsql') {
            return 'CAST(' . $column . ' AS TEXT) COLLATE "C" = ?';
        }
        throw new RuntimeException('Unsupported database driver for exact text matching');
    }

    /**
     * @param array<int, string> $conditions
     * @param array<int, mixed> $values
     */
    private function expectedCondition(array &$conditions, array &$values, string $column, mixed $expected, bool $exactText = false): void
    {
        if ($expected === null) {
            $conditions[] = "$column IS NULL";
        } else {
            $conditions[] = $exactText ? $this->strictTextMatch($column) : "$column = ?";
            $values[] = $expected;
        }
    }
}
