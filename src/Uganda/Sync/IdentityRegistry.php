<?php

declare(strict_types=1);

namespace Uganda\Sync;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class IdentityRegistry
{
    public function __construct(private PDO $pdo, private MappingDefinition $mapping, private string $scope = 'default')
    {
        if ($scope === '' || strlen($scope) > 80 || !preg_match('/^[A-Za-z0-9:_-]+$/D', $scope)) {
            throw new InvalidArgumentException('Invalid mapping scope');
        }
    }

    /**
     * Record a human-reviewed local key. Never infer keys from names or fuzzy matches.
     * Only existing records may be mapped. The caller must explicitly invoke this write.
     */
    public function approve(string $sourceKey, string $level, string $targetKey, string $reviewer): void
    {
        $levels = $this->mapping->levels();
        if (!isset($levels[$level]) || $sourceKey === '' || $targetKey === '' ||
            strlen($targetKey) > 191 || trim($reviewer) === '' || strlen($reviewer) > 191) {
            throw new InvalidArgumentException('Incomplete or unsupported identity approval');
        }
        $definition = $levels[$level];
        $inspector = new SchemaInspector($this->pdo);
        $inspector->assertMappedColumns($this->mapping);
        $table = $inspector->quote($definition['table']);
        $key = $inspector->quote($definition['key']);
        $statement = $this->pdo->prepare("SELECT $key FROM $table WHERE $key = ?");
        $statement->execute([$targetKey]);
        if ($statement->fetchColumn() === false || $statement->fetchColumn() !== false) {
            throw new RuntimeException('Identity mapping requires exactly one existing target record');
        }
        $sql = 'INSERT INTO uganda_geo_identity_maps
            (mapping_scope, source_hash, source_key, level, target_table, target_key, reviewer, approved)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)';
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                $this->scope, hash('sha256', $sourceKey), $sourceKey, $level,
                $definition['table'], $targetKey, $reviewer,
            ]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function mappedKey(string $sourceKey, string $level): ?string
    {
        $definition = $this->mapping->levels()[$level] ?? null;
        if ($definition === null) {
            return null;
        }
        $statement = $this->pdo->prepare('SELECT source_key, target_key FROM uganda_geo_identity_maps
            WHERE mapping_scope = ? AND source_hash = ? AND level = ? AND target_table = ? AND approved = 1');
        $statement->execute([$this->scope, hash('sha256', $sourceKey), $level, $definition['table']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if ($row['source_key'] !== $sourceKey) {
            throw new RuntimeException('Identity digest collision or corrupted mapping');
        }
        return (string) $row['target_key'];
    }
}
