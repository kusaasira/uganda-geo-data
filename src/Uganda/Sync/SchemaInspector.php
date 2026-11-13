<?php

declare(strict_types=1);

namespace Uganda\Sync;

use PDO;
use PDOStatement;
use RuntimeException;

final class SchemaInspector
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, list<string>> */
    public function tables(): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $rows = $this->queryRows("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $nameField = 'name';
        } elseif ($driver === 'mysql') {
            $rows = $this->queryRows("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name");
            $nameField = 'table_name';
        } elseif ($driver === 'pgsql') {
            $rows = $this->queryRows("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' ORDER BY table_name");
            $nameField = 'table_name';
        } else {
            throw new RuntimeException('Unsupported database driver: ' . $driver);
        }
        $tables = [];
        foreach ($rows as $row) {
            $name = $row[$nameField] ?? null;
            if (!is_string($name)) {
                continue;
            }
            try {
                MappingDefinition::assertIdentifier($name);
            } catch (\InvalidArgumentException $exception) {
                continue;
            }
            $tables[$name] = $this->columns($name);
        }
        return $tables;
    }

    /** @return list<string> */
    public function columns(string $table): array
    {
        MappingDefinition::assertIdentifier($table);
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $rows = $this->queryRows('PRAGMA table_info("' . $table . '")');
            $field = 'name';
        } elseif ($driver === 'mysql') {
            $rows = $this->preparedRows('SELECT column_name FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position', [$table]);
            $field = 'column_name';
        } elseif ($driver === 'pgsql') {
            $rows = $this->preparedRows('SELECT column_name FROM information_schema.columns
                WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position', [$table]);
            $field = 'column_name';
        } else {
            throw new RuntimeException('Unsupported database driver: ' . $driver);
        }
        $names = [];
        foreach ($rows as $row) {
            if (is_string($row[$field] ?? null)) {
                $names[] = $row[$field];
            }
        }
        return $names;
    }

    /** @return array<string, list<array{table: string, columns: list<string>, score: int}>> */
    public function candidates(): array
    {
        $result = [];
        $tables = $this->tables();
        foreach (['district', 'county', 'subcounty', 'parish', 'village'] as $level) {
            $candidates = [];
            foreach ($tables as $table => $columns) {
                $score = 0;
                if (str_contains(strtolower($table), $level)) {
                    $score += 5;
                }
                if (preg_match('/geo|location|administrative/i', $table)) {
                    ++$score;
                }
                foreach ($columns as $column) {
                    if (str_contains(strtolower($column), $level)) {
                        $score += 2;
                    }
                }
                if ($score > 0) {
                    $candidates[] = ['table' => $table, 'columns' => $columns, 'score' => $score];
                }
            }
            usort($candidates, static fn (array $a, array $b): int =>
                ($b['score'] <=> $a['score']) ?: strcmp($a['table'], $b['table']));
            $result[$level] = $candidates;
        }
        return $result;
    }

    public function assertMappedColumns(MappingDefinition $mapping): void
    {
        $tables = $this->tables();
        foreach ($mapping->levels() as $level => $definition) {
            $available = $tables[$definition['table']] ?? null;
            if ($available === null) {
                throw new RuntimeException('Missing mapped table for ' . $level);
            }
            foreach (['key', 'name', 'parent'] as $field) {
                if (isset($definition[$field]) && !in_array($definition[$field], $available, true)) {
                    throw new RuntimeException('Missing mapped ' . $field . ' column for ' . $level);
                }
            }
            if ($mapping->strategy() === 'existing') {
                $this->assertUniqueKey($definition['table'], $definition['key']);
                $this->assertTransactional($definition['table']);
            }
        }
    }

    public function assertUniqueKey(string $table, string $key): void
    {
        MappingDefinition::assertIdentifier($table);
        MappingDefinition::assertIdentifier($key);
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $valid = false;
        if ($driver === 'sqlite') {
            $rows = $this->queryRows('PRAGMA table_info("' . $table . '")');
            $pk = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['pk'] ?? 0) > 0));
            $valid = count($pk) === 1 && $pk[0]['name'] === $key;
            if (!$valid) {
                $indexes = $this->queryRows('PRAGMA index_list("' . $table . '")');
                foreach ($indexes as $index) {
                    if ((int) ($index['unique'] ?? 0) !== 1 || !is_string($index['name'] ?? null)) {
                        continue;
                    }
                    $indexName = str_replace('"', '""', $index['name']);
                    $columns = $this->queryRows('PRAGMA index_info("' . $indexName . '")');
                    if (count($columns) === 1 && $columns[0]['name'] === $key) {
                        $valid = true;
                        break;
                    }
                }
            }
        } elseif ($driver === 'mysql') {
            $rows = $this->preparedRows('SELECT index_name FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND non_unique = 0
                GROUP BY index_name HAVING COUNT(*) = 1 AND MAX(column_name) = ?', [$table, $key]);
            $valid = $rows !== [];
        } elseif ($driver === 'pgsql') {
            $rows = $this->preparedRows("SELECT tc.constraint_name
                FROM information_schema.table_constraints tc
                JOIN information_schema.key_column_usage kcu
                  ON tc.constraint_name = kcu.constraint_name
                 AND tc.table_schema = kcu.table_schema AND tc.table_name = kcu.table_name
                WHERE tc.table_schema = current_schema() AND tc.table_name = ?
                  AND tc.constraint_type IN ('PRIMARY KEY', 'UNIQUE')
                GROUP BY tc.constraint_name HAVING COUNT(*) = 1 AND MAX(kcu.column_name) = ?", [$table, $key]);
            $valid = $rows !== [];
        }
        if (!$valid) {
            throw new RuntimeException('Mapped local key must have a single-column unique constraint: ' . $table . '.' . $key);
        }
    }

    public function assertTransactional(string $table): void
    {
        MappingDefinition::assertIdentifier($table);
        if ((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $rows = $this->preparedRows('SELECT engine FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
            if (count($rows) !== 1 || strtolower((string) $rows[0]['engine']) !== 'innodb') {
                throw new RuntimeException('Existing target must use the transactional InnoDB engine');
            }
        }
    }

    public function quote(string $identifier): string
    {
        MappingDefinition::assertIdentifier($identifier);
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            return chr(96) . $identifier . chr(96);
        }
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            return '"' . $identifier . '"';
        }
        throw new RuntimeException('Unsupported database driver: ' . $driver);
    }

    /** @return list<array<string, mixed>> */
    private function queryRows(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Schema metadata query failed');
        }
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<string> $values
     * @return list<array<string, mixed>>
     */
    private function preparedRows(string $sql, array $values): array
    {
        $statement = $this->pdo->prepare($sql);
        if (!$statement instanceof PDOStatement || !$statement->execute($values)) {
            throw new RuntimeException('Schema metadata query failed');
        }
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
