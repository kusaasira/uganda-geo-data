<?php

declare(strict_types=1);

namespace Uganda\Data;

final class SnapshotReleaseAuditor
{
    /**
     * Classify a canonical snapshot diff for release review without redefining
     * the underlying CanonicalSnapshotDiffer contract.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    public function audit(array $before, array $after): array
    {
        $report = (new CanonicalSnapshotDiffer())->compare($before, $after);
        /** @var array<string, array<int, array{id: string, field: string, before: mixed, after: mixed}>> $groups */
        $groups = [
            'renamed' => [],
            'reparented' => [],
            'lifecycle_changed' => [],
            'provenance_changed' => [],
        ];

        $changes = $report['changes'];
        if (!is_array($changes)) {
            throw new \UnexpectedValueException('Canonical diff returned an invalid change list');
        }
        foreach ($changes as $change) {
            if (!is_array($change) || ($change['kind'] ?? '') !== 'modified' ||
                !is_array($change['fields'] ?? null) || !is_string($change['id'] ?? null)) {
                continue;
            }
            $fields = $change['fields'];
            $id = $change['id'];
            foreach ([
                'name' => 'renamed',
                'parent_id' => 'reparented',
                'lifecycle_status' => 'lifecycle_changed',
                'source_id' => 'provenance_changed',
                'source_record_id' => 'provenance_changed',
            ] as $field => $category) {
                if (!isset($fields[$field]) || !is_array($fields[$field])) {
                    continue;
                }
                $values = $fields[$field];
                $groups[$category][] = [
                    'id' => $id,
                    'field' => $field,
                    'before' => $values['before'] ?? null,
                    'after' => $values['after'] ?? null,
                ];
            }
        }

        foreach ($groups as &$records) {
            usort($records, static function (array $a, array $b): int {
                return strcmp((string) $a['id'], (string) $b['id']) ?:
                    strcmp((string) $a['field'], (string) $b['field']);
            });
        }
        unset($records);

        return [
            'before_snapshot_id' => $report['before_snapshot_id'],
            'after_snapshot_id' => $report['after_snapshot_id'],
            'before_total' => $report['before_count'],
            'after_total' => $report['after_count'],
            'summary' => $report['summary'],
            'groups' => $groups,
            'changes' => $report['changes'],
            'status' => $report['changes'] === [] ? 'unchanged' : 'review_required',
            'warning' => 'Absent records do not prove legal retirement or an administrative boundary change.',
        ];
    }
}
