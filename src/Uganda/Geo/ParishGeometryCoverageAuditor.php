<?php

declare(strict_types=1);

namespace Uganda\Geo;

use InvalidArgumentException;

final class ParishGeometryCoverageAuditor
{
    /**
     * Audit verified matches and unresolved source features without duplicating
     * the mapping rules owned by ParishBoundaryMatcher.
     *
     * @param array<int|string, array<string, mixed>> $features
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $decisions
     * @param array<int, mixed> $unresolvedSourceIds
     * @return array<string, mixed>
     */
    public function audit(array $features, array $snapshot, array $decisions, array $unresolvedSourceIds = []): array
    {
        if (array_keys($unresolvedSourceIds) !== array_keys(array_values($unresolvedSourceIds))) {
            throw new InvalidArgumentException('Unresolved source IDs must be an indexed list');
        }
        $mapping = (new ParishBoundaryMatcher())->match($features, $snapshot, $decisions);
        $sourceIds = $mapping['unmatched_source_object_ids'];
        if (!is_array($sourceIds)) {
            throw new InvalidArgumentException('Matcher returned an invalid source inventory');
        }
        $unmatched = [];
        foreach ($sourceIds as $sourceId) {
            if (!is_string($sourceId) || !(preg_match('/\A[0-9]+\z/D', $sourceId) === 1) || isset($unmatched[$sourceId])) {
                throw new InvalidArgumentException('Matcher returned invalid or duplicate source identities');
            }
            $unmatched[$sourceId] = true;
        }
        $unresolved = [];
        foreach ($unresolvedSourceIds as $index => $id) {
            if (!is_string($id) || !ctype_digit($id) || !isset($unmatched[$id]) ||
                isset($unresolved[$id])) {
                throw new InvalidArgumentException("Invalid, duplicate or already matched unresolved source ID at index $index");
            }
            $unresolved[$id] = true;
        }

        $unknown = array_keys(array_diff_key($unmatched, $unresolved));
        $unresolvedIds = array_keys($unresolved);
        $unknown = array_map('strval', $unknown);
        $unresolvedIds = array_map('strval', $unresolvedIds);
        sort($unknown, SORT_STRING);
        sort($unresolvedIds, SORT_STRING);

        return [
            'source_feature_count' => $mapping['source_feature_count'],
            'canonical_parish_ward_count' => $mapping['canonical_parish_ward_count'],
            'verified_count' => $mapping['matched_count'],
            'verified_matches' => $mapping['matches'],
            'unresolved_source_object_ids' => $unresolvedIds,
            'unmatched_source_object_ids' => $unknown,
            'unmapped_canonical_unit_ids' => $mapping['unmatched_canonical_unit_ids'],
            'coverage' => $mapping['status'] === 'complete' && $unresolvedIds === [] ? 'complete' : 'incomplete',
            'limitations' => 'Matching confidence and counts do not establish polygon topology, legal boundary accuracy or source-wide completeness.',
        ];
    }
}
