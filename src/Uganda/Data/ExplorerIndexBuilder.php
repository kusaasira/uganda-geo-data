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
        if (($snapshot['schema_version'] ?? null) !== 1
            || !is_string($snapshot['snapshot_id'] ?? null)
            || trim($snapshot['snapshot_id']) === ''
            || !isset($snapshot['source_ids']) || !is_array($snapshot['source_ids'])
            || $snapshot['source_ids'] === []
            || !isset($snapshot['units']) || !is_array($snapshot['units'])) {
            throw new InvalidArgumentException('Expected a versioned canonical snapshot with non-empty identity and units');
        }
        foreach ($snapshot['source_ids'] as $sourceId) {
            if (!is_string($sourceId) || trim($sourceId) === '') {
                throw new InvalidArgumentException('Invalid canonical source identifier');
            }
        }
        $index = [];
        foreach ($snapshot['units'] as $unit) {
            if (!is_array($unit) || !isset($unit['id'], $unit['name'], $unit['level'])
                || !is_string($unit['id']) || trim($unit['id']) === ''
                || !is_string($unit['name']) || trim($unit['name']) === ''
                || !is_string($unit['level']) || trim($unit['level']) === ''
                || !array_key_exists('parent_id', $unit)
                || !isset($unit['source_id']) || !is_string($unit['source_id'])
                || !in_array($unit['source_id'], $snapshot['source_ids'], true)
                || isset($index[$unit['id']])) {
                throw new InvalidArgumentException('Invalid or duplicate canonical unit');
            }
            $parent = $unit['parent_id'] ?? null;
            if ($parent !== null && (!is_string($parent) || trim($parent) === '')) {
                throw new InvalidArgumentException('Canonical parent identity must be a string or null');
            }
            if ($parent === null && !in_array($unit['level'], ['region', 'district', 'city'], true)) {
                throw new InvalidArgumentException('Non-root unit is missing a canonical parent: ' . $unit['id']);
            }
            $index[$unit['id']] = [
                'id' => $unit['id'],
                'name' => $unit['name'],
                'level' => $unit['level'],
                'parent_id' => $parent,
            ];
        }
        // Prevent a "complete hierarchy" screen from displaying silently
        // truncated chains when parents are missing or cyclic.
        foreach ($index as $id => $unit) {
            $parentId = $unit['parent_id'];
            if ($parentId === null) {
                continue;
            }
            if ($parentId === '' || !isset($index[$parentId])) {
                throw new InvalidArgumentException('Missing canonical parent for unit ' . $id);
            }
            $visited = [$id => true];
            while ($parentId !== null) {
                if (isset($visited[$parentId])) {
                    throw new InvalidArgumentException('Cyclic canonical ancestry at unit ' . $id);
                }
                $visited[$parentId] = true;
                $parentId = $index[$parentId]['parent_id'];
            }
        }
        ksort($index, SORT_STRING);

        return [
            'snapshot_id' => $snapshot['snapshot_id'],
            'reference_date' => is_string($snapshot['reference_date'] ?? null) ? $snapshot['reference_date'] : null,
            'unit_count' => count($index),
            'units' => array_values($index),
            'limitations' => 'The indexed source snapshot may not reflect current legal or geospatial boundaries.',
        ];
    }
}
