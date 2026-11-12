<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class ExplorerCoverageSummary
{
    /**
     * Publish aggregate verified mapping counts only. No reviewer identities,
     * evidence URLs or individual unmatched source objects reach the browser.
     *
     * @param array<string, mixed> $index
     * @param array<string, mixed> $report
     * @return array<string, int|string>
     */
    public function summarize(array $index, array $report): array
    {
        $units = $index['units'] ?? null;
        if (!is_array($units)) {
            throw new InvalidArgumentException('Invalid explorer unit inventory');
        }
        $ids = [];
        foreach ($units as $unit) {
            if (!is_array($unit) || !is_string($unit['id'] ?? null)) {
                throw new InvalidArgumentException('Invalid canonical unit in explorer');
            }
            if (in_array($unit['level'] ?? null, ['parish', 'ward'], true)) {
                $ids[$unit['id']] = true;
            }
        }
        $total = count($ids);
        $matches = $report['verified_matches'] ?? null;
        $unmapped = $report['unmapped_canonical_unit_ids'] ?? null;
        $unmatched = $report['unmatched_source_object_ids'] ?? null;
        $unresolved = $report['unresolved_source_object_ids'] ?? null;
        $sourceCount = $report['source_feature_count'] ?? null;
        $mappingStatus = $report['coverage'] ?? null;
        if (!is_int($sourceCount) || $sourceCount < 0
            || !is_array($unmatched) || !is_array($unresolved)) {
            throw new InvalidArgumentException('Incomplete source-side geometry coverage evidence');
        }
        if (!is_int($report['canonical_parish_ward_count'] ?? null)
            || $total !== $report['canonical_parish_ward_count']
            || !is_int($report['verified_count'] ?? null)
            || !is_array($matches) || !is_array($unmapped)
            || (!is_string($mappingStatus) || !in_array($mappingStatus, ['complete', 'incomplete'], true))) {
            throw new InvalidArgumentException('Coverage report does not match canonical parish/ward inventory');
        }
        $verified = [];
        foreach ($matches as $match) {
            if (!is_array($match)) {
                throw new InvalidArgumentException('Invalid verified geometry match');
            }
            $id = $match['unit_id'] ?? null;
            if (!is_string($id) || !isset($ids[$id]) || isset($verified[$id])) {
                throw new InvalidArgumentException('Invalid or duplicate verified geometry unit');
            }
            $verified[$id] = true;
        }
        $missing = [];
        foreach ($unmapped as $id) {
            if (!is_string($id) || !isset($ids[$id]) || isset($missing[$id]) || isset($verified[$id])) {
                throw new InvalidArgumentException('Invalid or duplicate unmapped geometry unit');
            }
            $missing[$id] = true;
        }
        $sourceIds = [];
        foreach ($matches as $match) {
            $sourceId = $match['source_object_id'] ?? null;
            if (!is_string($sourceId) || $sourceId === '' || isset($sourceIds[$sourceId])) {
                throw new InvalidArgumentException('Duplicate or missing verified source geometry identity');
            }
            $sourceIds[$sourceId] = true;
        }
        foreach (array_merge($unmatched, $unresolved) as $sourceId) {
            if (!is_string($sourceId) || $sourceId === '' || isset($sourceIds[$sourceId])) {
                throw new InvalidArgumentException('Duplicate or invalid unmatched source geometry identity');
            }
            $sourceIds[$sourceId] = true;
        }
        if (count($verified) !== $report['verified_count']
            || count($verified) + count($missing) !== $total
            || count($sourceIds) !== $sourceCount
            || ($mappingStatus === 'complete' &&
                ($missing !== [] || $unmatched !== [] || $unresolved !== [] || $verified === []))) {
            throw new InvalidArgumentException('Inconsistent geometry coverage partition');
        }

        return [
            'reviewed' => count($verified),
            'total' => $total,
            'unmapped' => count($missing),
            'source_feature_count' => $sourceCount,
            'unmatched_source_features' => count($unmatched) + count($unresolved),
            'matching_status' => $mappingStatus,
            'scope' => 'supplied_canonical_snapshot_only',
            'national_coverage' => 'unverified',
        ];
    }
}
