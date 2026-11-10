<?php

declare(strict_types=1);

namespace Uganda\Geo;

use RuntimeException;

final class ArcGisLayerMetadata
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $expected
     */
    public static function assertMatches(array $metadata, array $expected): void
    {
        if (isset($metadata['error']) || !isset($metadata['id'], $metadata['name'], $metadata['geometryType'])) {
            throw new RuntimeException('Invalid ArcGIS layer metadata');
        }
        if (($metadata['name'] ?? null) !== ($expected['label'] ?? null) ||
            ($metadata['geometryType'] ?? null) !== ($expected['geometry_type'] ?? null) ||
            ($metadata['objectIdField'] ?? null) !== ($expected['object_id_field'] ?? null)) {
            throw new RuntimeException('ArcGIS layer identity does not match the catalogued source');
        }
        $limit = $metadata['maxRecordCount'] ?? null;
        if (!is_int($limit) || $limit < 1) {
            throw new RuntimeException('ArcGIS layer reports an invalid result limit');
        }
        $wkid = $metadata['extent']['spatialReference']['latestWkid'] ??
            $metadata['extent']['spatialReference']['wkid'] ?? null;
        if (($expected['source_crs'] ?? null) === 'EPSG:3857' &&
            !in_array($wkid, [3857, 102100], true)) {
            throw new RuntimeException('ArcGIS source CRS differs from the expected Web Mercator reference');
        }
        $fields = [];
        foreach ($metadata['fields'] ?? [] as $field) {
            if (is_array($field) && isset($field['name']) && is_string($field['name'])) {
                $fields[$field['name']] = true;
            }
        }
        foreach ($expected['expected_fields'] ?? [] as $field) {
            if (!isset($fields[$field])) {
                throw new RuntimeException("ArcGIS layer missing expected field $field");
            }
        }
    }
}
