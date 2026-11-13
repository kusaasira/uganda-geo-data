<?php

declare(strict_types=1);

namespace Uganda\Sync;

use PDO;
use RuntimeException;

final class SchemaInspector
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<string, array<int, string>> */
    public function tables(): array
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $names = $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($driver === 'mysql') {
            $names = $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($driver === 'pgsql') {
            $names = $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        } else {
            throw new RuntimeException('Unsupported database driver: ' . $driver);
        }
        $tables = [];
        foreach ($names as $name) {
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

    /** @return array<int, string> */
    public function columns(string $table): array
    {
        MappingDefinition::assertIdentifier($table);
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $query = $this->pdo->query('PRAGMA table_info("' . $table . '")');
            return array_values(array_column($query->fetchAll(PDO::FETCH_ASSOC), 'name'));
        }
        if ($driver === 'mysql') {
            $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ordinal_position';
        } elseif ($driver === 'pgsql') {
            $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position';
        } else {
            throw new RuntimeException('Unsupported database driver: ' . $driver);
        }
        $query = $this->pdo->prepare($sql);
        $query->execute([$table]);
        return array_values($query->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function candidates(): array
    {
        $result = [];
        foreach (['district', 'county', 'subcounty', 'parish', 'village'] as $level) {
            $result[$level] = [];
            foreach ($this->tables() as $table => $columns) {
                $score = 0;
                if (str_contains(strtolower($table), $level)) {
                    $score += 5;
                }
                if (preg_match('/geo|location|administrative/i', $table)) {
                    $score += 1;
                }
                foreach ($columns as $column) {
                    if (str_contains(strtolower($column), $level)) {
                        $score += 2;
                    }
                }
                if ($score > 0) {
                    $result[$level][] = ['table' => $table, 'columns' => $columns, 'score' => $score];
                }
            }
            usort($result[$level], static fn (array $a, array $b): int =>
                ($b['score'] <=> $a['score']) ?: strcmp($a['table'], $b['table']));
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
        }
    }

    public function quote(string $identifier): string
    {
        MappingDefinition::assertIdentifier($identifier);
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            return chr(96) . $identifier . chr(96);
        }
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            return '"' . $identifier . '"';
        }
        throw new RuntimeException('Unsupported database driver: ' . $driver);
    }
}
