<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class AdministrativeCountReconciler
{
    /**
     * Compare published aggregate totals against a preserved canonical snapshot.
     *
     * A difference in totals is not evidence that individual records are absent.
     * Published years, administrative categories and urban classifications may
     * differ from the undated legacy snapshot.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $references
     * @return array<string, mixed>
     */
    public function reconcile(array $snapshot, array $references): array
    {
        if (!isset($snapshot['units']) || !is_array($snapshot['units'])) {
            throw new InvalidArgumentException('Canonical snapshot requires a units list');
        }
        if (!isset($references['publications']) || !is_array($references['publications']) ||
            $references['publications'] === []) {
            throw new InvalidArgumentException('Reference catalogue requires publications');
        }

        $legacyCounts = [];
        $seenUnitIds = [];
        if (array_keys($snapshot['units']) !== array_keys(array_values($snapshot['units']))) {
            throw new InvalidArgumentException('Canonical snapshot units must be a list');
        }
        foreach ($snapshot['units'] as $index => $unit) {
            if (!is_array($unit) || !isset($unit['id'], $unit['level']) ||
                !is_string($unit['id']) || $unit['id'] === '' ||
                !is_string($unit['level']) || $unit['level'] === '') {
                throw new InvalidArgumentException("Invalid canonical unit at index $index");
            }
            if (isset($seenUnitIds[$unit['id']])) {
                throw new InvalidArgumentException('Duplicate canonical unit ID: ' . $unit['id']);
            }
            $seenUnitIds[$unit['id']] = true;
            $level = $unit['level'];
            $legacyCounts[$level] = ($legacyCounts[$level] ?? 0) + 1;
        }
        ksort($legacyCounts);

        $reports = [];
        $seenPublications = [];
        foreach ($references['publications'] as $index => $publication) {
            if (!is_array($publication) || !isset($publication['id'], $publication['url'], $publication['counts']) ||
                !is_string($publication['id']) || !is_string($publication['url']) ||
                !is_array($publication['counts']) || $publication['counts'] === []) {
                throw new InvalidArgumentException("Invalid UBOS reference at index $index");
            }
            if (isset($seenPublications[$publication['id']])) {
                throw new InvalidArgumentException('Duplicate UBOS publication ID: ' . $publication['id']);
            }
            $seenPublications[$publication['id']] = true;
            $comparisons = [];
            foreach ($publication['counts'] as $category => $expected) {
                if (!is_string($category) || !is_int($expected) || $expected < 0) {
                    throw new InvalidArgumentException('Reference counts must be nonnegative integers');
                }
                $legacyLevel = ['subcounty_division' => 'subcounty', 'parish_ward' => 'parish'][$category] ?? $category;
                $observed = $legacyCounts[$legacyLevel] ?? null;
                $comparisons[$category] = [
                    'reference_count' => $expected,
                    'legacy_level' => $legacyLevel,
                    'legacy_count' => $observed,
                    'arithmetic_difference' => $observed === null ? null : $observed - $expected,
                    'classification_verified' => false,
                    'record_level_match_verified' => false,
                ];
            }
            $reports[] = [
                'id' => $publication['id'],
                'url' => $publication['url'],
                'reference_year' => $publication['reference_year'] ?? null,
                'geography_basis' => $publication['geography_basis'] ?? null,
                'comparisons' => $comparisons,
            ];
        }

        return [
            'snapshot_id' => $snapshot['snapshot_id'] ?? null,
            'total_legacy_records' => count($snapshot['units']),
            'legacy_counts' => $legacyCounts,
            'reports' => $reports,
            'status' => 'aggregate_baseline_only',
            'caution' => 'Aggregate totals cannot establish missing units, authoritative IDs or current boundaries.',
        ];
    }
}
