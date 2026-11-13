<?php

declare(strict_types=1);

namespace Uganda\Sync;

use PDO;
use RuntimeException;

final class SyncPlanner
{
    public function __construct(
        private PDO $pdo,
        private MappingDefinition $mapping,
        private string $scope = 'default',
    ) {
    }

    /**
     * Analyze database state without modifications. Only an explicit executor may write.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function plan(array $snapshot): array
    {
        $catalog = new SnapshotCatalog();
        $catalog->validate($snapshot);
        $inspector = new SchemaInspector($this->pdo);
        $inspector->assertMappedColumns($this->mapping);

        $strategy = $this->mapping->strategy();
        $definitions = $this->mapping->levels();
        $registry = new IdentityRegistry($this->pdo, $this->mapping, $this->scope);
        $existingManaged = [];
        if ($strategy !== 'existing') {
            $query = $this->pdo->query('SELECT source_hash, source_key, level, name, parent_hash FROM uganda_geo_units');
            while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
                $existingManaged[$row['source_hash']] = $row;
            }
        }

        $operations = [];
        $conflicts = [];
        $unchanged = 0;
        $unmapped = 0;
        $snapshotHash = $catalog->digest($snapshot);

        foreach ($snapshot['units'] as $unit) {
            $level = $unit['level'];
            if (!isset($definitions[$level])) {
                continue;
            }
            $key = $unit['id'];
            $sourceHash = hash('sha256', $key);
            if ($strategy !== 'existing') {
                $desired = [
                    'source_hash' => $sourceHash,
                    'source_key' => $key,
                    'level' => $level,
                    'name' => $unit['name'],
                    'parent_hash' => $unit['parent_id'] === null ? null : hash('sha256', $unit['parent_id']),
                    'snapshot_hash' => $snapshotHash,
                ];
                $before = $existingManaged[$sourceHash] ?? null;
                if ($before !== null && $before['source_key'] !== $key) {
                    throw new RuntimeException('Canonical source identity hash collision');
                }
                if ($before === null) {
                    $operations[] = ['kind' => 'insert_managed', 'after' => $desired];
                } elseif ($before['name'] !== $desired['name'] ||
                    $before['parent_hash'] !== $desired['parent_hash'] ||
                    $before['level'] !== $desired['level']) {
                    $operations[] = ['kind' => 'update_managed', 'before' => $before, 'after' => $desired];
                } else {
                    ++$unchanged;
                }
                continue;
            }

            $definition = $definitions[$level];
            $targetKey = $registry->mappedKey($key, $level);
            if ($targetKey === null) {
                ++$unmapped;
                continue;
            }
            $table = $inspector->quote($definition['table']);
            $idColumn = $inspector->quote($definition['key']);
            $nameColumn = $inspector->quote($definition['name']);
            $parentColumn = isset($definition['parent']) ? ', ' . $inspector->quote($definition['parent']) : '';
            $statement = $this->pdo->prepare("SELECT $idColumn, $nameColumn$parentColumn FROM $table WHERE $idColumn = ?");
            $statement->execute([$targetKey]);
            $before = $statement->fetch(PDO::FETCH_ASSOC);
            if ($before === false || $statement->fetch(PDO::FETCH_ASSOC) !== false) {
                $conflicts[] = ['source_key' => $key, 'reason' => 'Missing or nonunique approved local record'];
                continue;
            }
            $newParent = null;
            $oldParent = isset($definition['parent']) ? $before[$definition['parent']] : null;
            if (isset($definition['parent'])) {
                if ($unit['parent_id'] !== null) {
                    $newParent = $registry->mappedKey($unit['parent_id'], $definition['parent_level']);
                    if ($newParent === null) {
                        $conflicts[] = ['source_key' => $key, 'reason' => 'Unmapped target parent'];
                        continue;
                    }
                }
            }
            $nameChange = $before[$definition['name']] !== $unit['name'];
            $parentChange = isset($definition['parent']) && $oldParent !== $newParent;
            if (($nameChange && ($definition['write_name'] ?? false) !== true) ||
                ($parentChange && ($definition['write_parent'] ?? false) !== true)) {
                $conflicts[] = ['source_key' => $key, 'reason' => 'Destination differs in a non-writable field'];
                continue;
            }
            if (!$nameChange && !$parentChange) {
                ++$unchanged;
                continue;
            }
            $operations[] = [
                'kind' => 'update_existing',
                'level' => $level,
                'source_key' => $key,
                'target_key' => $targetKey,
                'before_name' => $before[$definition['name']],
                'after_name' => $unit['name'],
                'before_parent' => $oldParent,
                'after_parent' => $newParent,
                'update_name' => $nameChange,
                'update_parent' => $parentChange,
            ];
        }

        $plan = [
            'format_version' => 1,
            'scope' => $this->scope,
            'strategy' => $strategy,
            'unmapped_policy' => $this->mapping->unmappedPolicy(),
            'snapshot_id' => $snapshot['snapshot_id'],
            'snapshot_hash' => $snapshotHash,
            'mapping_hash' => $this->mapping->fingerprint(),
            'operations' => $operations,
            'conflicts' => $conflicts,
            'unmapped' => $unmapped,
            'unchanged' => $unchanged,
        ];
        $plan['plan_hash'] = hash('sha256', json_encode($plan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $plan;
    }
}
