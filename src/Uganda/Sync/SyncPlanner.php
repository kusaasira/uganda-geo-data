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
     * @return array{format_version: int, scope: string, strategy: string, unmapped_policy: string,
     *     snapshot_id: string, snapshot_hash: string, mapping_hash: string,
     *     operations: list<array<string, mixed>>, conflicts: list<array<string, string>>,
     *     unmapped: int, unchanged: int, plan_hash: string}
     */
    public function plan(array $snapshot): array
    {
        $catalog = new SnapshotCatalog();
        $units = $catalog->units($snapshot);
        $snapshotId = $snapshot['snapshot_id'] ?? null;
        if (!is_string($snapshotId)) {
            throw new RuntimeException('Missing validated source snapshot identity');
        }
        $inspector = new SchemaInspector($this->pdo);
        $inspector->assertMappedColumns($this->mapping);

        $strategy = $this->mapping->strategy();
        $definitions = $this->mapping->levels();
        $registry = new IdentityRegistry($this->pdo, $this->mapping, $this->scope);
        $approvedKeys = $strategy === 'existing' ? $registry->allMappedKeys() : [];
        $targetRows = $strategy === 'existing' ? $this->loadTargetRows($approvedKeys, $inspector) : [];
        $existingManaged = [];
        if ($strategy !== 'existing') {
            $query = $this->pdo->query('SELECT source_hash, source_key, level, name, parent_hash FROM uganda_geo_units');
            if (!$query instanceof \PDOStatement) {
                throw new RuntimeException('Cannot read existing managed geography');
            }
            while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (!is_string($row['source_hash'] ?? null) || !is_string($row['source_key'] ?? null) ||
                    !is_string($row['level'] ?? null) || !is_string($row['name'] ?? null)) {
                    throw new RuntimeException('Corrupt managed administrative record');
                }
                $existingManaged[$row['source_hash']] = $row;
            }
        }

        $operations = [];
        $conflicts = [];
        $unchanged = 0;
        $unmapped = 0;
        $snapshotHash = $catalog->digest($snapshot);

        foreach ($units as $unit) {
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
            $targetKey = $approvedKeys[$level][$key] ?? null;
            if ($targetKey === null) {
                ++$unmapped;
                continue;
            }
            $before = $targetRows[$level][$targetKey] ?? null;
            if ($before === null) {
                $conflicts[] = ['source_key' => $key, 'reason' => 'Missing or nonunique approved local record'];
                continue;
            }
            $newParent = null;
            $oldParent = isset($definition['parent']) ? $before[$definition['parent']] : null;
            if (isset($definition['parent'])) {
                if ($unit['parent_id'] !== null) {
                    $parentLevel = $definition['parent_level'] ?? null;
                    if ($parentLevel === null) {
                        throw new RuntimeException('Missing parent level for mapped foreign key');
                    }
                    $newParent = $approvedKeys[$parentLevel][$unit['parent_id']] ?? null;
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
            'snapshot_id' => $snapshotId,
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

    /**
     * Only retrieve named, already reviewed destination rows. Bound parameters
     * and batched IN queries avoid unbounded per-location network round trips.
     *
     * @param array<string, array<string, string>> $approvedKeys
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function loadTargetRows(array $approvedKeys, SchemaInspector $inspector): array
    {
        $result = [];
        foreach ($this->mapping->levels() as $level => $definition) {
            $keys = array_values(array_unique(array_values($approvedKeys[$level] ?? [])));
            if ($keys === []) {
                continue;
            }
            $table = $inspector->quote($definition['table']);
            $keyColumn = $inspector->quote($definition['key']);
            $nameColumn = $inspector->quote($definition['name']);
            $parentColumn = isset($definition['parent']) ? ', ' . $inspector->quote($definition['parent']) : '';
            foreach (array_chunk($keys, 200) as $batch) {
                $placeholders = implode(', ', array_fill(0, count($batch), '?'));
                $statement = $this->pdo->prepare("SELECT $keyColumn, $nameColumn$parentColumn
                    FROM $table WHERE $keyColumn IN ($placeholders)");
                $statement->execute($batch);
                while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $nativeKey = $row[$definition['key']] ?? null;
                    if (!is_string($nativeKey) && !is_int($nativeKey)) {
                        throw new RuntimeException('Invalid mapped local primary key');
                    }
                    $key = (string) $nativeKey;
                    if (isset($result[$level][$key])) {
                        throw new RuntimeException('Nonunique local primary key encountered');
                    }
                    $result[$level][$key] = $row;
                }
            }
        }
        return $result;
    }
}
