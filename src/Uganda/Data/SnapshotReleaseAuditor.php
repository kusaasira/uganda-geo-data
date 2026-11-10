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
        $groups = [
            'renamed' => [],
            'reparented' => [],
            'lifecycle_changed' => [],
            'provenance_changed' => [],
        ];

        foreach ($report['changes'] as $change) {
            if (($change['kind'] ?? '') !== 'modified') {
                continue;
            }
            foreach ([
                'name' => 'renamed',
                'parent_id' => 'reparented',
                'lifecycle_status' => 'lifecycle_changed',
                'source_id' => 'provenance_changed',
                'source_record_id' => 'provenance_changed',
            ] as $field => $category) {
                if (!isset($change['fields'][$field])) {
                    continue;
                }
                $values = $change['fields'][$field];
                $groups[$category][] = [
                    'id' => $change['id'],
                    'field' => $field,
                    'before' => $values['before'],
                    'after' => $values['after'],
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
