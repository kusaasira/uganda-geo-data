<?php

declare(strict_types=1);

namespace Uganda\Geo;

use InvalidArgumentException;

final class GeometryExportFields
{
    /**
     * Project reviewed GeoJSON to a stable Shapefile-compatible DBF schema.
     * No geometry can be emitted without a canonical reviewed unit identity.
     *
     * @param array<string, mixed> $collection
     * @return array<string, mixed>
     */
    public function prepare(array $collection): array
    {
        $metadata = $collection['metadata'] ?? null;
        if (($collection['type'] ?? null) !== 'FeatureCollection'
            || !is_array($metadata)
            || ($metadata['verification_scope'] ?? null) !== 'canonical_identity_and_parent_only'
            || !isset($collection['features']) || !is_array($collection['features'])
            || $collection['features'] === []) {
            throw new InvalidArgumentException('Expected reviewed GeoJSON features with identity verification');
        }
        $features = [];
        $ids = [];
        foreach ($collection['features'] as $feature) {
            if (!is_array($feature) || ($feature['type'] ?? null) !== 'Feature'
                || !is_array($feature['properties'] ?? null) || !is_array($feature['geometry'] ?? null)) {
                throw new InvalidArgumentException('Invalid reviewed parish feature');
            }
            $original = $feature['properties'];
            foreach (['unit_id', 'parent_unit_id', 'source_id', 'source_object_id', 'evidence_date'] as $field) {
                if (!is_string($original[$field] ?? null) || trim($original[$field]) === '') {
                    throw new InvalidArgumentException('Missing reviewed geometry property: ' . $field);
                }
                // DBF text fields cannot hold more than 254 bytes. Reject
                // oversize UTF-8 values before GDAL can silently truncate.
                if (strlen($original[$field]) > 254) {
                    throw new InvalidArgumentException('Reviewed geometry property exceeds DBF limit: ' . $field);
                }
            }
            if (isset($ids[$original['unit_id']])) {
                throw new InvalidArgumentException('Duplicate reviewed parish identity');
            }
            $ids[$original['unit_id']] = true;
            GeometryContract::assertGeometry($feature['geometry']);
            $geometry = $feature['geometry'];
            // The published distribution is intentionally two-dimensional.
            // Reject elevations rather than allowing a GIS driver to remove Z.
            /** @var array<int, array<int, array<int, array<int, int|float>>>> $polygons */
            $polygons = $geometry['type'] === 'Polygon'
                ? [$geometry['coordinates']]
                : $geometry['coordinates'];
            foreach ($polygons as $polygon) {
                foreach ($polygon as $ring) {
                    foreach ($ring as $position) {
                        if (count($position) !== 2) {
                            throw new InvalidArgumentException('Expected two-dimensional parish coordinates');
                        }
                    }
                }
            }
            $features[] = [
                'type' => 'Feature',
                'id' => $original['unit_id'],
                'properties' => [
                    'unit_id' => $original['unit_id'],
                    'parent_id' => $original['parent_unit_id'],
                    'source_id' => $original['source_id'],
                    'src_obj_id' => $original['source_object_id'],
                    'evid_date' => $original['evidence_date'],
                ],
                'geometry' => $feature['geometry'],
            ];
        }
        usort($features, static function (array $a, array $b): int {
            return strcmp($a['id'], $b['id']);
        });

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /** @return array<string, string> */
    public function dictionary(): array
    {
        return [
            'unit_id' => 'Canonical unit identifier',
            'parent_id' => 'Canonical parent unit identifier',
            'source_id' => 'Source registry identifier',
            'src_obj_id' => 'Source feature object identifier',
            'evid_date' => 'Date of human-reviewed matching evidence',
        ];
    }
}
