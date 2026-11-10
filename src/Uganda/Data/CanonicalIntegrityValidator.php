<?php

declare(strict_types=1);

namespace Uganda\Data;

final class CanonicalIntegrityValidator
{
    /**
     * Validate identity, provenance, parent links, cycles and record conservation.
     * The method reports failures without dropping or repairing any input row.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, int>|null $expectedCountsByLevel
     * @return array<string, mixed>
     */
    public function validate(array $snapshot, ?array $expectedCountsByLevel = null): array
    {
        $errors = [];
        if (($snapshot['schema_version'] ?? null) !== 1) {
            $errors[] = 'Unsupported snapshot schema version';
        }
        if (!isset($snapshot['snapshot_id']) || !is_string($snapshot['snapshot_id']) || trim($snapshot['snapshot_id']) === '') {
            $errors[] = 'Snapshot requires a nonempty snapshot_id';
        }
        $units = $snapshot['units'] ?? null;
        if (!is_array($units)) {
            return ['valid' => false, 'total_units' => 0, 'counts_by_level' => [], 'errors' => ['Snapshot units must be an array']];
        }
        if ($units === [] || array_keys($units) !== array_keys(array_values($units))) {
            $errors[] = 'Snapshot units must be a nonempty JSON list';
        }
        $sources = $snapshot['source_ids'] ?? null;
        if (!is_array($sources) || $sources === []) {
            $errors[] = 'Snapshot requires nonempty source_ids';
            $sources = [];
        } else {
            if (array_keys($sources) !== array_keys(array_values($sources)) ||
                count($sources) !== count(array_unique($sources, SORT_REGULAR))) {
                $errors[] = 'Snapshot source_ids must be a list of distinct identifiers';
            }
            foreach ($sources as $source) {
                if (!is_string($source) || trim($source) === '') {
                    $errors[] = 'Snapshot source_ids must be nonempty strings';
                }
            }
        }

        $byId = [];
        $sourceRows = [];
        $counts = [];
        foreach ($units as $index => $unit) {
            if (!is_array($unit)) {
                $errors[] = "Unit at index $index must be an object";
                continue;
            }
            foreach (['id', 'level', 'name', 'source_id', 'source_record_id', 'lifecycle_status'] as $key) {
                if (!isset($unit[$key]) || !is_string($unit[$key]) || trim($unit[$key]) === '') {
                    $errors[] = "Unit at index $index has invalid $key";
                }
            }
            $id = $unit['id'] ?? null;
            $level = $unit['level'] ?? null;
            if (!is_string($id) || $id === '' || !is_string($level) || $level === '') {
                continue;
            }
            if (isset($byId[$id])) {
                $errors[] = "Duplicate administrative unit ID: $id";
                continue;
            }
            $byId[$id] = $unit;
            $counts[$level] = ($counts[$level] ?? 0) + 1;
            $sourceRecord = $unit['source_record_id'] ?? null;
            $sourceId = $unit['source_id'] ?? null;
            if (is_string($sourceId) && $sourceId !== '' && is_string($sourceRecord) && $sourceRecord !== '') {
                $origin = $sourceId . ':' . $level . ':' . $sourceRecord;
                if (isset($sourceRows[$origin])) {
                    $errors[] = "Duplicate origin record $origin";
                }
                $sourceRows[$origin] = true;
            }
            if (!in_array($unit['lifecycle_status'] ?? null, ['active', 'retired', 'disputed'], true)) {
                $errors[] = "Invalid lifecycle status for unit $id";
            }
            if (isset($unit['verification_status']) &&
                !in_array($unit['verification_status'], ['unverified', 'verified', 'disputed'], true)) {
                $errors[] = "Invalid verification status for unit $id";
            }
            foreach (['aliases', 'external_codes'] as $field) {
                if (!array_key_exists($field, $unit) || !is_array($unit[$field])) {
                    $errors[] = "Invalid $field for unit $id";
                }
            }
            if (!in_array($unit['source_id'] ?? null, $sources, true)) {
                $errors[] = "Unregistered source for unit $id";
            }
            if (!array_key_exists('parent_id', $unit) || ($unit['parent_id'] !== null && !is_string($unit['parent_id']))) {
                $errors[] = "Invalid parent ID for unit $id";
            }
        }

        foreach ($byId as $id => $unit) {
            $parent = $unit['parent_id'] ?? null;
            if (is_string($parent) && !isset($byId[$parent])) {
                $errors[] = "Unresolved parent $parent for unit $id";
                continue;
            }
            $seen = [];
            $cursor = $id;
            $depth = 0;
            while (isset($byId[$cursor])) {
                if (++$depth > 32) {
                    $errors[] = "Parent chain exceeds maximum depth for unit $id";
                    break;
                }
                if (isset($seen[$cursor])) {
                    $errors[] = "Cycle detected in parent chain from $id";
                    break;
                }
                $seen[$cursor] = true;
                $next = $byId[$cursor]['parent_id'] ?? null;
                if (!is_string($next) || !isset($byId[$next])) {
                    break;
                }
                $cursor = $next;
            }
        }

        if ($expectedCountsByLevel !== null) {
            foreach ($expectedCountsByLevel as $level => $expected) {
                $actual = $counts[$level] ?? 0;
                if ($actual !== $expected) {
                    $errors[] = "Record conservation failure for $level: expected $expected, found $actual";
                }
            }
            foreach ($counts as $level => $actual) {
                if (!array_key_exists($level, $expectedCountsByLevel)) {
                    $errors[] = "Unaccounted output level $level with $actual records";
                }
            }
        }
        ksort($counts);

        return [
            'valid' => $errors === [],
            'total_units' => count($units),
            'counts_by_level' => $counts,
            'errors' => array_values(array_unique($errors)),
        ];
    }
}
