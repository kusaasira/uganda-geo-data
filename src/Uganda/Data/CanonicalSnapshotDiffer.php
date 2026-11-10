<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class CanonicalSnapshotDiffer
{
    /**
     * Produce a deterministic, identity-preserving diff between two snapshots.
     * A missing row is not automatically evidence of legal retirement.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    public function compare(array $before, array $after): array
    {
        $old = $this->index($before);
        $new = $this->index($after);
        $changes = [];
        foreach ($old as $id => $unit) {
            if (!isset($new[$id])) {
                $changes[] = ['id' => $id, 'kind' => 'missing_from_next_snapshot',
                    'before' => $unit, 'after' => null];
                continue;
            }
            $current = $new[$id];
            $fields = [];
            foreach (['name', 'level', 'parent_id', 'lifecycle_status', 'aliases', 'external_codes',
                'source_id', 'source_record_id', 'verification_status'] as $field) {
                if (($unit[$field] ?? null) !== ($current[$field] ?? null)) {
                    $fields[$field] = ['before' => $unit[$field] ?? null, 'after' => $current[$field] ?? null];
                }
            }
            if ($fields !== []) {
                $changes[] = ['id' => $id, 'kind' => 'modified', 'fields' => $fields];
            }
        }
        foreach ($new as $id => $unit) {
            if (!isset($old[$id])) {
                $changes[] = ['id' => $id, 'kind' => 'added', 'before' => null, 'after' => $unit];
            }
        }
        usort($changes, static function (array $left, array $right): int {
            return strcmp($left['id'], $right['id']);
        });

        $summary = ['added' => 0, 'missing_from_next_snapshot' => 0, 'modified' => 0];
        foreach ($changes as $change) {
            ++$summary[$change['kind']];
        }

        return [
            'before_snapshot_id' => $before['snapshot_id'] ?? null,
            'after_snapshot_id' => $after['snapshot_id'] ?? null,
            'before_count' => count($old),
            'after_count' => count($new),
            'summary' => $summary,
            'changes' => $changes,
            'interpretation' => 'Differences describe snapshot content, not necessarily legal administrative changes.',
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, array<string, mixed>>
     */
    private function index(array $snapshot): array
    {
        if (!isset($snapshot['units']) || !is_array($snapshot['units']) ||
            array_keys($snapshot['units']) !== array_keys(array_values($snapshot['units']))) {
            throw new InvalidArgumentException('Snapshot units must be a list');
        }
        $index = [];
        foreach ($snapshot['units'] as $position => $unit) {
            if (!is_array($unit) || !isset($unit['id']) || !is_string($unit['id']) ||
                $unit['id'] === '') {
                throw new InvalidArgumentException("Invalid unit ID at index $position");
            }
            if (isset($index[$unit['id']])) {
                throw new InvalidArgumentException('Duplicate snapshot ID: ' . $unit['id']);
            }
            $index[$unit['id']] = $unit;
        }
        ksort($index);
        return $index;
    }
}
