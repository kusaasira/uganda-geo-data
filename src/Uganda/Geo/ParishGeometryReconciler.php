<?php

declare(strict_types=1);

namespace Uganda\Geo;

final class ParishGeometryReconciler
{
    /**
     * Review explicit polygon matches against canonical parish IDs and parent IDs.
     * A GeoJSON object ID identifies a source feature, not an official parish.
     *
     * @param array<int, array<string, mixed>> $units
     * @param array<int|string, array<string, mixed>> $features
     * @param array<int, array<string, mixed>> $decisions
     * @return array<string, mixed>
     */
    public function reconcile(array $units, array $features, array $decisions): array
    {
        $errors = [];
        $parishes = [];
        foreach ($units as $index => $unit) {
            if (($unit['level'] ?? null) !== 'parish') {
                continue;
            }
            $id = $unit['id'] ?? null;
            $parentId = $unit['parent_id'] ?? null;
            if (!is_string($id) || $id === '' || !is_string($parentId) || $parentId === '' ||
                isset($parishes[$id])) {
                $errors[] = "Invalid or duplicate canonical parish at index $index";
                continue;
            }
            $parishes[$id] = $parentId;
        }

        $featureIds = [];
        foreach ($features as $key => $feature) {
            $objectId = (string) $key;
            if ($objectId === '' || !ctype_digit($objectId)) {
                $errors[] = "Invalid source feature ID $objectId";
                continue;
            }
            $featureIds[$objectId] = true;
        }

        $seenObjects = [];
        $seenParishes = [];
        $verified = [];
        $unresolved = [];
        foreach ($decisions as $index => $decision) {
            $objectId = $decision['source_object_id'] ?? null;
            if (!is_string($objectId) || !ctype_digit($objectId) ||
                !isset($featureIds[$objectId]) || isset($seenObjects[$objectId])) {
                $errors[] = "Missing or duplicated source polygon at decision $index";
                continue;
            }
            $seenObjects[$objectId] = true;
            $status = $decision['status'] ?? null;
            $id = $decision['canonical_parish_id'] ?? null;
            if ($status === 'unresolved') {
                if ($id !== null) {
                    $errors[] = "Unresolved polygon $objectId cannot claim a parish ID";
                }
                $unresolved[] = $objectId;
                continue;
            }
            $parentId = $decision['canonical_parent_id'] ?? null;
            $evidenceUrl = $decision['evidence_url'] ?? null;
            $date = $decision['evidence_date'] ?? null;
            $parsed = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            if ($status !== 'verified' || !is_string($id) || !isset($parishes[$id]) ||
                !is_string($parentId) || $parishes[$id] !== $parentId ||
                !is_string($evidenceUrl) || strpos($evidenceUrl, 'https://') !== 0 ||
                $parsed === false || $parsed->format('Y-m-d') !== $date) {
                $errors[] = "Unverified parish identity or ancestry at decision $index";
                continue;
            }
            if (isset($seenParishes[$id])) {
                $errors[] = "Several source polygons claim canonical parish $id without review";
            }
            $seenParishes[$id] = true;
            $verified[] = $objectId;
        }

        // Numeric string array keys are cast to integers by PHP.
        // Expose source object IDs as strings consistently in review reports.
        $unmatched = array_map('strval', array_keys(array_diff_key($featureIds, $seenObjects)));
        $missingParishes = array_keys(array_diff_key($parishes, $seenParishes));
        sort($verified);
        sort($unresolved);
        sort($unmatched);
        sort($missingParishes);
        return [
            'valid' => $errors === [],
            'coverage' => $missingParishes === [] && $unmatched === [] && $unresolved === [] && $errors === [] ? 'complete' : 'incomplete',
            'source_polygon_count' => count($features),
            'canonical_parish_count' => count($parishes),
            'verified_source_object_ids' => $verified,
            'unresolved_source_object_ids' => $unresolved,
            'unmatched_source_object_ids' => $unmatched,
            'unmapped_parish_ids' => $missingParishes,
            'errors' => array_values(array_unique($errors)),
        ];
    }
}
