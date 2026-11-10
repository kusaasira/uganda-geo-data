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
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function build(array $features, array $report, string $sourceId): array
    {
        if (trim($sourceId) === '' || !isset($report['matches']) || !is_array($report['matches'])) {
            throw new InvalidArgumentException('Source identity and a reviewed matching report are required');
        }

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
                    'reviewer' => (string) $match['reviewer'],
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
                'verified_feature_count' => count($output),
                'coverage_status' => $report['status'] ?? 'incomplete',
                'publication_rights' => 'review_required',
            ],
        ];
    }
}
