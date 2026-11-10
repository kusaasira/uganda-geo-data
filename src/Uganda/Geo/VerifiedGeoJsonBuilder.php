<?php

declare(strict_types=1);

namespace Uganda\Geo;

use InvalidArgumentException;

final class VerifiedGeoJsonBuilder
{
    /**
     * Create a deterministic local GeoJSON distribution using only explicitly
     * reviewed polygon-to-unit matches. A local artifact is not publication approval.
     *
     * @param array<int|string, array<string, mixed>> $features
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $decisions
     * @return array<string, mixed>
     */
    public function build(array $features, array $snapshot, array $decisions, string $sourceId): array
    {
        if (trim($sourceId) === '') {
            throw new InvalidArgumentException('Source identity is required');
        }

        // Never accept a caller-supplied "verified" result. Re-evaluate every
        // decision against the canonical units and actual source features.
        /** @var array{
         *   matches: array<int, array<string, mixed>>,
         *   status: string,
         *   unmatched_source_object_ids: array<int, string>,
         *   unmatched_canonical_unit_ids: array<int, string>
         * } $report
         */
        $report = (new ParishBoundaryMatcher())->match($features, $snapshot, $decisions);

        $output = [];
        $usedUnits = [];
        $usedFeatures = [];
        foreach ($report['matches'] as $match) {
            if (!is_array($match) || !isset($match['source_object_id'], $match['unit_id'],
                $match['parent_unit_id'], $match['evidence_url'], $match['evidence_date'], $match['reviewer'])) {
                throw new InvalidArgumentException('Incomplete verified source match');
            }

            $objectId = (string) $match['source_object_id'];
            $unitId = (string) $match['unit_id'];
            if (!ctype_digit($objectId) || !isset($features[$objectId]) || isset($usedFeatures[$objectId])
                || $unitId === '' || isset($usedUnits[$unitId])) {
                throw new InvalidArgumentException('Unknown or duplicate source feature or canonical unit');
            }
            $feature = $features[$objectId];
            if (!is_array($feature) || !isset($feature['geometry']) || !is_array($feature['geometry'])) {
                throw new InvalidArgumentException('Verified feature is missing polygon geometry');
            }
            GeometryContract::assertGeometry($feature['geometry']);
            $usedFeatures[$objectId] = true;
            $usedUnits[$unitId] = true;
            $output[] = [
                'type' => 'Feature',
                'id' => $unitId,
                'properties' => [
                    'unit_id' => $unitId,
                    'parent_unit_id' => (string) $match['parent_unit_id'],
                    'source_id' => $sourceId,
                    'source_object_id' => $objectId,
                    'evidence_url' => (string) $match['evidence_url'],
                    'evidence_date' => (string) $match['evidence_date'],
                ],
                'geometry' => $feature['geometry'],
            ];
        }

        usort($output, static function (array $a, array $b): int {
            return strcmp($a['id'], $b['id']);
        });

        return [
            'type' => 'FeatureCollection',
            'features' => $output,
            'metadata' => [
                'source_id' => $sourceId,
                'reviewed_identity_count' => count($output),
                'topology_status' => 'not_checked',
                'verification_scope' => 'canonical_identity_and_parent_only',
                'coverage_status' => $report['status'],
                'unmatched_source_feature_count' => count($report['unmatched_source_object_ids']),
                'unmatched_canonical_unit_count' => count($report['unmatched_canonical_unit_ids']),
                'publication_rights' => 'review_required',
            ],
        ];
    }
}
