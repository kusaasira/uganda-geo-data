<?php

declare(strict_types=1);

namespace Uganda\Sync;

use InvalidArgumentException;

final class MappingDefinition
{
    private const LEVELS = ['district', 'county', 'subcounty', 'parish', 'village'];

    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        if (!in_array($config['strategy'] ?? null, ['managed', 'existing', 'hybrid'], true)) {
            throw new InvalidArgumentException('Mapping requires managed, existing or hybrid strategy');
        }
        if (!isset($config['levels']) || !is_array($config['levels']) || $config['levels'] === []) {
            throw new InvalidArgumentException('Mapping requires one or more levels');
        }
        foreach ($config['levels'] as $level => $mapping) {
            if (!in_array($level, self::LEVELS, true) || !is_array($mapping)) {
                throw new InvalidArgumentException('Unsupported administrative level');
            }
            foreach (['table', 'key', 'name'] as $field) {
                self::assertIdentifier($mapping[$field] ?? null);
            }
            if (isset($mapping['parent'])) {
                self::assertIdentifier($mapping['parent']);
                if (!isset($mapping['parent_level']) || !in_array($mapping['parent_level'], self::LEVELS, true)) {
                    throw new InvalidArgumentException('Mapped parent requires a supported parent level');
                }
            } elseif (isset($mapping['parent_level'])) {
                throw new InvalidArgumentException('Parent level requires a parent column');
            }
            if ($mapping['key'] === $mapping['name'] || (isset($mapping['parent']) &&
                in_array($mapping['parent'], [$mapping['key'], $mapping['name']], true))) {
                throw new InvalidArgumentException('Mapped columns must be distinct');
            }
        }
        $this->config = $config;
    }

    public static function assertIdentifier($identifier): void
    {
        if (!is_string($identifier) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,62}$/D', $identifier)) {
            throw new InvalidArgumentException('Unsafe or unsupported SQL identifier');
        }
    }

    public static function managed(): self
    {
        return new self([
            'strategy' => 'managed',
            'levels' => [
                'district' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name'],
                'county' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_key', 'parent_level' => 'district'],
                'subcounty' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_key', 'parent_level' => 'county'],
                'parish' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_key', 'parent_level' => 'subcounty'],
                'village' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_key', 'parent_level' => 'parish'],
            ],
        ]);
    }

    public function strategy(): string
    {
        return $this->config['strategy'];
    }

    /** @return array<string, array<string, string>> */
    public function levels(): array
    {
        return $this->config['levels'];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->config, JSON_THROW_ON_ERROR));
    }
}
