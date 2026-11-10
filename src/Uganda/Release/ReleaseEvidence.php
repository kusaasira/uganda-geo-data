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

        $topologyStatus = 'unverified';
        if ($topology !== null) {
            if (!is_int($topology['feature_count'] ?? null)
                || !is_int($topology['failed_count'] ?? null)
                || $topology['feature_count'] < 1 || $topology['failed_count'] < 0
                || $topology['failed_count'] > $topology['feature_count']) {
                throw new InvalidArgumentException('Invalid topology evidence');
            }
            $topologyStatus = $topology['failed_count'] === 0 ? 'passed' : 'failed';
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
