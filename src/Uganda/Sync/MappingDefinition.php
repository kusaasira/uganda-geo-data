<?php

declare(strict_types=1);

namespace Uganda\Sync;

use InvalidArgumentException;

/**
 * @phpstan-type LevelMapping array{table: string, key: string, name: string,
 *     parent?: string, parent_level?: string, scope_column?: string, write_name?: bool, write_parent?: bool}
 * @phpstan-type MappingConfig array{strategy: string, levels: array<string, LevelMapping>,
 *     unmapped_policy?: string, external_levels?: array<string, LevelMapping>}
 */
final class MappingDefinition
{
    private const LEVELS = ['district', 'county', 'subcounty', 'parish', 'village'];

    /** @var MappingConfig */
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
            if (isset($mapping['scope_column'])) {
                self::assertIdentifier($mapping['scope_column']);
                if (in_array($mapping['scope_column'], [$mapping['key'], $mapping['name']], true)) {
                    throw new InvalidArgumentException('Scope column must be distinct from identity and name columns');
                }
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
                in_array($mapping['parent'], [$mapping['key'], $mapping['name'], $mapping['scope_column'] ?? null], true))) {
                throw new InvalidArgumentException('Mapped columns must be distinct');
            }
        }
        if (!in_array($config['unmapped_policy'] ?? 'block', ['block', 'ignore'], true)) {
            throw new InvalidArgumentException('Unsupported unmapped record policy');
        }
        foreach ($config['levels'] as $definition) {
            foreach (['write_name', 'write_parent'] as $flag) {
                if (isset($definition[$flag]) && !is_bool($definition[$flag])) {
                    throw new InvalidArgumentException('Field write permissions must be explicit booleans');
                }
            }
        }
        if ($config['strategy'] !== 'existing') {
            foreach ($config['levels'] as $definition) {
                if ($definition['table'] !== 'uganda_geo_units' ||
                    $definition['key'] !== 'source_key' ||
                    $definition['name'] !== 'name' ||
                    (isset($definition['parent']) && $definition['parent'] !== 'parent_hash') ||
                    isset($definition['scope_column']) ||
                    ($definition['write_name'] ?? false) ||
                    ($definition['write_parent'] ?? false)) {
                    throw new InvalidArgumentException('Managed and hybrid source levels must use Uganda Geo owned columns');
                }
            }
        }
        if ($config['strategy'] === 'hybrid' && isset($config['external_levels'])) {
            if (!is_array($config['external_levels']) || $config['external_levels'] === []) {
                throw new InvalidArgumentException('Hybrid external levels must be a nonempty mapping');
            }
            new self(['strategy' => 'existing', 'levels' => $config['external_levels']]);
            foreach ($config['external_levels'] as $definition) {
                if (!is_array($definition)) {
                    throw new InvalidArgumentException('Malformed hybrid external mapping');
                }
                if (($definition['write_name'] ?? false) || ($definition['write_parent'] ?? false)) {
                    throw new InvalidArgumentException('Hybrid crosswalk targets are read-only');
                }
            }
        } elseif (isset($config['external_levels'])) {
            throw new InvalidArgumentException('External levels are supported only by hybrid integration');
        }
        /** @var MappingConfig $config */
        $this->config = $config;
    }

    public static function assertIdentifier(mixed $identifier): void
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
                'county' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_hash', 'parent_level' => 'district'],
                'subcounty' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_hash', 'parent_level' => 'county'],
                'parish' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_hash', 'parent_level' => 'subcounty'],
                'village' => ['table' => 'uganda_geo_units', 'key' => 'source_key', 'name' => 'name', 'parent' => 'parent_hash', 'parent_level' => 'parish'],
            ],
        ]);
    }

    public function strategy(): string
    {
        return $this->config['strategy'];
    }

    /** @return array<string, LevelMapping> */
    public function levels(): array
    {
        return $this->config['levels'];
    }

    /** @return array<string, LevelMapping> */
    public function externalLevels(): array
    {
        return $this->config['external_levels'] ?? [];
    }

    /** @return array<string, LevelMapping> */
    public function targetLevels(): array
    {
        if ($this->strategy() === 'hybrid') {
            return $this->externalLevels();
        }
        if ($this->strategy() === 'existing') {
            return $this->levels();
        }
        throw new InvalidArgumentException('Managed source tables do not require external identity approvals');
    }

    public function unmappedPolicy(): string
    {
        return $this->config['unmapped_policy'] ?? 'block';
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->config, JSON_THROW_ON_ERROR));
    }
}
