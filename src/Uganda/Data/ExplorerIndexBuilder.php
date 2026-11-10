<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class ExplorerIndexBuilder
{
    /**
     * Build a minimal, deterministic public index from an explicitly provided
     * canonical snapshot without exposing unrelated source metadata.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function build(array $snapshot): array
    {
        if (!isset($snapshot['units']) || !is_array($snapshot['units'])) {
            throw new InvalidArgumentException('Canonical snapshot must contain a units list');
        }
        $index = [];
        foreach ($snapshot['units'] as $unit) {
            if (!is_array($unit) || !isset($unit['id'], $unit['name'], $unit['level'])
                || !is_string($unit['id']) || trim($unit['id']) === ''
                || !is_string($unit['name']) || trim($unit['name']) === ''
                || !is_string($unit['level']) || trim($unit['level']) === ''
                || isset($index[$unit['id']])) {
                throw new InvalidArgumentException('Invalid or duplicate canonical unit');
            }
            $parent = $unit['parent_id'] ?? null;
            if ($parent !== null && !is_string($parent)) {
                throw new InvalidArgumentException('Canonical parent identity must be a string or null');
            }
            $index[$unit['id']] = [
                'id' => $unit['id'],
                'name' => $unit['name'],
                'level' => $unit['level'],
                'parent_id' => $parent,
            ];
        }
        ksort($index, SORT_STRING);

        return [
            'snapshot_id' => is_string($snapshot['snapshot_id'] ?? null) ? $snapshot['snapshot_id'] : 'unspecified',
            'unit_count' => count($index),
            'units' => array_values($index),
            'limitations' => 'The indexed source snapshot may not reflect current legal or geospatial boundaries.',
        ];
    }
}
