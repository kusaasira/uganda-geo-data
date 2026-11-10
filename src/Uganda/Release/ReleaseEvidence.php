<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class ReleaseEvidence
{
    /**
     * Aggregate independently generated QA reports without treating sample
     * coverage as national coverage or source access as publishing permission.
     *
     * @param array<string, mixed> $canonical
     * @param array<string, mixed>|null $topology
     * @param array<string, mixed>|null $coverage
     * @return array<string, mixed>
     */
    public function aggregate(array $canonical, ?array $topology, ?array $coverage): array
    {
        if (!is_bool($canonical['valid'] ?? null)
            || !is_int($canonical['total_units'] ?? null)
            || $canonical['total_units'] < 0) {
            throw new InvalidArgumentException('Invalid canonical integrity evidence');
        }

        if ($canonical['valid'] && is_array($canonical['errors'] ?? null)
            && $canonical['errors'] !== []) {
            throw new InvalidArgumentException('Canonical report contradicts its own error list');
        }

        $topologyStatus = 'unverified';
        if ($topology !== null) {
            $results = $topology['results'] ?? null;
            if (!is_int($topology['feature_count'] ?? null)
                || !is_int($topology['failed_count'] ?? null)
                || $topology['feature_count'] < 1 || $topology['failed_count'] < 0
                || $topology['failed_count'] > $topology['feature_count']
                || !is_array($results) || count($results) !== $topology['feature_count']) {
                throw new InvalidArgumentException('Invalid topology evidence');
            }
            $failed = 0;
            $indices = [];
            foreach ($results as $result) {
                if (!is_array($result) || !is_int($result['feature_index'] ?? null)
                    || isset($indices[$result['feature_index']])
                    || !is_array($result['audit'] ?? null)
                    || !in_array($result['audit']['status'] ?? null, ['passed', 'failed'], true)
                    || !is_array($result['audit']['issues'] ?? null)) {
                    throw new InvalidArgumentException('Malformed topology feature evidence');
                }
                $indices[$result['feature_index']] = true;
                $hasIssues = $result['audit']['issues'] !== [];
                if (($result['audit']['status'] === 'failed') !== $hasIssues) {
                    throw new InvalidArgumentException('Contradictory feature topology status');
                }
                if ($hasIssues) {
                    $failed++;
                }
            }
            if ($failed !== $topology['failed_count']) {
                throw new InvalidArgumentException('Topology count contradicts feature-level evidence');
            }
            $topologyStatus = $failed === 0 ? 'passed' : 'failed';
        }

        $coverageStatus = 'unverified';
        if ($coverage !== null) {
            if (!in_array($coverage['coverage'] ?? null, ['complete', 'incomplete'], true)
                || !is_int($coverage['verified_count'] ?? null)
                || !is_int($coverage['canonical_parish_ward_count'] ?? null)
                || $coverage['verified_count'] < 0
                || $coverage['canonical_parish_ward_count'] < $coverage['verified_count']) {
                throw new InvalidArgumentException('Invalid parish mapping evidence');
            }
            $matches = $coverage['verified_matches'] ?? null;
            $unmapped = $coverage['unmapped_canonical_unit_ids'] ?? null;
            $unmatched = $coverage['unmatched_source_object_ids'] ?? null;
            $unresolved = $coverage['unresolved_source_object_ids'] ?? null;
            if (!is_array($matches) || !is_array($unmapped)
                || !is_array($unmatched) || !is_array($unresolved)
                || !is_int($coverage['source_feature_count'] ?? null)) {
                throw new InvalidArgumentException('Missing parish feature-level evidence');
            }
            $mappedIds = [];
            $sourceIds = [];
            foreach ($matches as $match) {
                if (!is_array($match) || !is_string($match['unit_id'] ?? null)
                    || !is_string($match['source_object_id'] ?? null)
                    || isset($mappedIds[$match['unit_id']])
                    || isset($sourceIds[$match['source_object_id']])) {
                    throw new InvalidArgumentException('Invalid or duplicate verified parish match');
                }
                $mappedIds[$match['unit_id']] = true;
                $sourceIds[$match['source_object_id']] = true;
            }
            if (count($matches) !== $coverage['verified_count']
                || count(array_unique($unmapped, SORT_REGULAR)) !== count($unmapped)
                || count(array_unique($unmatched, SORT_REGULAR)) !== count($unmatched)
                || count(array_unique($unresolved, SORT_REGULAR)) !== count($unresolved)
                || count($matches) + count($unmapped) !== $coverage['canonical_parish_ward_count']
                || count($matches) + count($unmatched) + count($unresolved) !== $coverage['source_feature_count']) {
                throw new InvalidArgumentException('Parish coverage counters contradict feature evidence');
            }
            foreach ($unmapped as $id) {
                if (!is_string($id) || isset($mappedIds[$id])) {
                    throw new InvalidArgumentException('Mapped and unmapped parish IDs overlap');
                }
            }
            $unmatchedIds = [];
            foreach (array_merge($unmatched, $unresolved) as $id) {
                if (!is_string($id) || isset($sourceIds[$id])) {
                    throw new InvalidArgumentException('Unmatched or unresolved source IDs overlap verified matches');
                }
                if (isset($unmatchedIds[$id])) {
                    throw new InvalidArgumentException('Duplicate source ID across unmatched/unresolved lists');
                }
                $unmatchedIds[$id] = true;
            }
            if ($coverage['coverage'] === 'complete' && ($unmapped !== [] || $unmatched !== []
                || $unresolved !== [] || $matches === [])) {
                throw new InvalidArgumentException('Complete parish coverage claim contradicts unresolved data');
            }
            $coverageStatus = $coverage['coverage'] === 'complete' ? 'reported_complete' : 'incomplete';
        }

        return [
            'schema_version' => 1,
            'checks' => [
                'canonical_integrity' => $canonical['valid'] ? 'passed' : 'failed',
                'feature_topology' => $topologyStatus,
                'input_parish_matching' => $coverageStatus,
                'national_boundary_coverage' => 'unverified',
                'publication_rights' => 'unverified',
                'published_package_install' => 'unverified',
            ],
            'evidence' => [
                'unit_count' => $canonical['total_units'],
                'topology_feature_count' => $topology['feature_count'] ?? null,
                'topology_failed_count' => $topology['failed_count'] ?? null,
                'verified_parish_count' => $coverage['verified_count'] ?? null,
                'input_parish_count' => $coverage['canonical_parish_ward_count'] ?? null,
            ],
            'release_ready' => false,
            'limitations' => 'Technical checks cannot certify national coverage, legal reuse rights or stable Composer installation.',
        ];
    }
}
