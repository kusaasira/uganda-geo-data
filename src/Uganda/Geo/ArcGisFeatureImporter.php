<?php

declare(strict_types=1);

namespace Uganda\Geo;

use InvalidArgumentException;
use JsonException;

final class ArcGisFeatureImporter
{
    /**
     * Validate one raw GeoJSON FeatureCollection and index each polygon by object ID.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function parsePage(string $raw, string $objectIdField): array
    {
        try {
            $collection = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Invalid GeoJSON response', 0, $exception);
        }
        if (!is_array($collection) || ($collection['type'] ?? null) !== 'FeatureCollection' ||
            !isset($collection['features']) || !is_array($collection['features']) ||
            !empty($collection['exceededTransferLimit'])) {
            throw new InvalidArgumentException('Response must be a complete GeoJSON FeatureCollection');
        }

        $features = [];
        foreach ($collection['features'] as $index => $feature) {
            if (!is_array($feature) || ($feature['type'] ?? null) !== 'Feature' ||
                !isset($feature['properties'], $feature['geometry']) ||
                !is_array($feature['properties']) || !is_array($feature['geometry'])) {
                throw new InvalidArgumentException("Malformed GeoJSON feature at index $index");
            }

            $rawId = $feature['properties'][$objectIdField] ?? $feature['id'] ?? null;
            if ((!is_int($rawId) && !is_string($rawId)) || !ctype_digit((string) $rawId)) {
                throw new InvalidArgumentException("Feature at index $index has no numeric object ID");
            }
            $id = (string) $rawId;
            if (isset($features[$id])) {
                throw new InvalidArgumentException("Duplicate GeoJSON feature ID $id");
            }

            GeometryContract::assertGeometry($feature['geometry']);
            $features[$id] = $feature;
        }

        return $features;
    }
}
