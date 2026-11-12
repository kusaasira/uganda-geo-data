<?php

declare(strict_types=1);

namespace Uganda\Geo;

use InvalidArgumentException;

final class ParishBoundaryMatcher
{
    /**
     * Resolve documented ArcGIS object IDs to known parish or ward units.
     * No geometry is assigned on the basis of a name-only comparison.
     *
     * @param array<int|string, array<string, mixed>> $features
     * @param array<string, mixed> $snapshot
     * @param array<int, mixed> $decisions
     * @return array<string, mixed>
     */
    public function match(array $features, array $snapshot, array $decisions = []): array
    {
        if (!isset($snapshot['units']) || !is_array($snapshot['units'])) {
            throw new InvalidArgumentException('Canonical snapshot requires a units list');
        }
        if (array_keys($decisions) !== array_keys(array_values($decisions))) {
            throw new InvalidArgumentException('Boundary decisions must be an indexed list');
        }
        $units = [];
        foreach ($snapshot['units'] as $index => $unit) {
            if (!is_array($unit) || !isset($unit['id'], $unit['level']) ||
                !is_string($unit['id']) || !is_string($unit['level'])) {
                throw new InvalidArgumentException("Invalid unit at index $index");
            }
            if (!in_array($unit['level'], ['parish', 'ward'], true)) {
                continue;
            }
            if (isset($units[$unit['id']])) {
                throw new InvalidArgumentException('Duplicate parish/ward ID ' . $unit['id']);
            }
            $units[$unit['id']] = $unit;
        }

        $featureIndex = [];
        foreach ($features as $sourceId => $feature) {
            if (preg_match('/\A[0-9]+\z/D', (string) $sourceId) !== 1) {
                throw new InvalidArgumentException('Feature index must use numeric source object IDs');
            }
            $id = (string) $sourceId;
            if (isset($featureIndex[$id])) {
                throw new InvalidArgumentException("Duplicate feature object ID $id");
            }
            $featureIndex[$id] = $feature;
        }

        $matches = [];
        $byObject = [];
        $byUnit = [];
        foreach ($decisions as $index => $decision) {
            if (!is_array($decision) || !isset($decision['object_id'], $decision['unit_id'],
                $decision['evidence_url'], $decision['evidence_date'], $decision['reviewer'],
                $decision['parent_unit_id']) ||
                (!is_int($decision['object_id']) && !is_string($decision['object_id'])) ||
                !is_string($decision['unit_id']) || !is_string($decision['evidence_url']) ||
                !is_string($decision['evidence_date']) || !is_string($decision['reviewer']) ||
                !is_string($decision['parent_unit_id'])) {
                throw new InvalidArgumentException("Invalid boundary mapping at index $index");
            }
            $objectId = (string) $decision['object_id'];
            $unitId = $decision['unit_id'];
            if (preg_match('/\A[0-9]+\z/D', $objectId) !== 1 || !isset($featureIndex[$objectId])) {
                throw new InvalidArgumentException("Unrecognized source feature $objectId");
            }
            if (!isset($units[$unitId])) {
                throw new InvalidArgumentException("Unknown canonical parish/ward $unitId");
            }
            if (($units[$unitId]['parent_id'] ?? null) !== $decision['parent_unit_id']) {
                throw new InvalidArgumentException("Parent chain mismatch for $unitId");
            }
            if (isset($byObject[$objectId]) || isset($byUnit[$unitId])) {
                throw new InvalidArgumentException('Ambiguous duplicate polygon-to-unit assignment');
            }
            if (filter_var($decision['evidence_url'], FILTER_VALIDATE_URL) === false ||
                parse_url($decision['evidence_url'], PHP_URL_SCHEME) !== 'https' ||
                trim($decision['reviewer']) === '' ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $decision['evidence_date']) ||
                !checkdate(
                    (int) substr($decision['evidence_date'], 5, 2),
                    (int) substr($decision['evidence_date'], 8, 2),
                    (int) substr($decision['evidence_date'], 0, 4)
                )) {
                throw new InvalidArgumentException("Mapping for $unitId requires valid dated HTTPS evidence");
            }

            $byObject[$objectId] = true;
            $byUnit[$unitId] = true;
            $matches[] = [
                'source_object_id' => $objectId,
                'unit_id' => $unitId,
                'parent_unit_id' => $decision['parent_unit_id'],
                'evidence_url' => $decision['evidence_url'],
                'evidence_date' => $decision['evidence_date'],
                'reviewer' => $decision['reviewer'],
            ];
        }

        usort($matches, static function (array $left, array $right): int {
            return strcmp($left['unit_id'], $right['unit_id']);
        });
        $unmatchedFeatures = array_map('strval', array_values(array_diff(array_keys($featureIndex), array_keys($byObject))));
        $unmatchedUnits = array_values(array_diff(array_keys($units), array_keys($byUnit)));
        sort($unmatchedFeatures, SORT_STRING);
        sort($unmatchedUnits, SORT_STRING);

        return [
            'source_feature_count' => count($featureIndex),
            'canonical_parish_ward_count' => count($units),
            'matched_count' => count($matches),
            'matches' => $matches,
            'unmatched_source_object_ids' => $unmatchedFeatures,
            'unmatched_canonical_unit_ids' => $unmatchedUnits,
            'status' => $featureIndex !== [] && $units !== [] && $unmatchedFeatures === [] && $unmatchedUnits === [] ? 'complete' : 'incomplete',
        ];
    }
}
