<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class CanonicalSnapshotDiffer
{
    /**
     * Deterministically compare immutable administrative snapshots by ID.
     * An absent record is not automatically an officially retired unit.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    public function diff(array $before, array $after): array
    {
        if (($before['schema_version'] ?? null) !== 1 || ($after['schema_version'] ?? null) !== 1 ||
            !isset($before['snapshot_id'], $after['snapshot_id']) ||
            !is_string($before['snapshot_id']) || !is_string($after['snapshot_id'])) {
            throw new InvalidArgumentException('Both snapshots require schema version 1 and snapshot identifiers');
        }
        $previous = $this->index($before);
        $current = $this->index($after);

        $added = array_keys(array_diff_key($current, $previous));
        $absent = array_keys(array_diff_key($previous, $current));
        sort($added);
        sort($absent);

        $renamed = [];
        $reparented = [];
        $lifecycle = [];
        $provenance = [];
        $unchanged = 0;

        foreach ($previous as $id => $old) {
            if (!isset($current[$id])) {
                continue;
            }
            $new = $current[$id];
            $changed = false;
            foreach ([
                'name' => 'renamed',
                'parent_id' => 'reparented',
                'lifecycle_status' => 'lifecycle',
                'source_id' => 'provenance'
            ] as $field => $category) {
                $oldValue = $old[$field];
                $newValue = $new[$field];
                if ($oldValue === $newValue) {
                    continue;
                }
                $record = ['id' => $id, 'before' => $oldValue, 'after' => $newValue];
                if ($category === 'renamed') {
                    $renamed[] = $record;
                } elseif ($category === 'reparented') {
                    $reparented[] = $record;
                } elseif ($category === 'lifecycle') {
                    $lifecycle[] = $record;
                } else {
                    $provenance[] = $record;
                }
                $changed = true;
            }
            if (!$changed) {
                ++$unchanged;
            }
        }
        foreach ([&$renamed, &$reparented, &$lifecycle, &$provenance] as &$records) {
            usort($records, static function (array $a, array $b): int {
                return strcmp((string) $a['id'], (string) $b['id']);
            });
        }
        unset($records);

        $changedCount = count($added) + count($absent) + count($renamed) +
            count($reparented) + count($lifecycle) + count($provenance);

        return [
            'before_snapshot_id' => $before['snapshot_id'],
            'after_snapshot_id' => $after['snapshot_id'],
            'before_total' => count($previous),
            'after_total' => count($current),
            'added_ids' => $added,
            'absent_ids' => $absent,
            'renamed' => $renamed,
            'reparented' => $reparented,
            'lifecycle_changed' => $lifecycle,
            'provenance_changed' => $provenance,
            'unchanged_count' => $unchanged,
            'status' => $changedCount === 0 ? 'unchanged' : 'review_required',
            'warning' => 'Absence is not retirement and arithmetic differences do not establish legal boundary changes.',
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, array<string, mixed>>
     */
    private function index(array $snapshot): array
    {
        $units = $snapshot['units'] ?? null;
        if (!is_array($units)) {
            throw new InvalidArgumentException('Snapshot units must be a list');
        }
        $indexed = [];
        foreach ($units as $position => $unit) {
            if (!is_array($unit) || !isset($unit['id'], $unit['name'],
                $unit['lifecycle_status'], $unit['source_id']) ||
                !is_string($unit['id']) || $unit['id'] === '' ||
                !is_string($unit['name']) || !is_string($unit['lifecycle_status']) ||
                !is_string($unit['source_id']) || !array_key_exists('parent_id', $unit) ||
                (!is_string($unit['parent_id']) && $unit['parent_id'] !== null)) {
                throw new InvalidArgumentException("Invalid canonical unit at row $position");
            }
            if (isset($indexed[$unit['id']])) {
                throw new InvalidArgumentException('Repeated canonical ID: ' . $unit['id']);
            }
            $indexed[$unit['id']] = $unit;
        }
        ksort($indexed);
        return $indexed;
    }
}
