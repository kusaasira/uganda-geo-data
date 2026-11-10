<?php

declare(strict_types=1);

namespace Uganda\Geo;

use InvalidArgumentException;

final class GeometryRoundTripVerifier
{
    /**
     * Compare the complete identity, provenance and approximate geometry
     * invariants after each GDAL conversion. Format drivers may reorder rings
     * or promote Polygon to MultiPolygon without altering the parcel.
     *
     * @param array<string, mixed> $prepared
     * @param array<string, mixed> $roundTrip
     */
    public function verify(array $prepared, array $roundTrip): void
    {
        $reference = $prepared['features'] ?? null;
        $actual = $roundTrip['features'] ?? null;
        if (!is_array($reference) || !is_array($actual) || count($reference) !== count($actual)) {
            throw new InvalidArgumentException('GIS conversion changed the feature count');
        }

        $expected = [];
        foreach ($reference as $feature) {
            if (!is_array($feature) || !is_string($feature['id'] ?? null)
                || !is_array($feature['properties'] ?? null)
                || !is_array($feature['geometry'] ?? null)) {
                throw new InvalidArgumentException('Invalid reference feature');
            }
            $id = $feature['id'];
            if (isset($expected[$id])) {
                throw new InvalidArgumentException('Duplicate reference unit');
            }
            $expected[$id] = $feature;
        }

        $seen = [];
        foreach ($actual as $feature) {
            if (!is_array($feature) || !is_array($feature['properties'] ?? null)
                || !is_array($feature['geometry'] ?? null)) {
                throw new InvalidArgumentException('Malformed GIS round-trip feature');
            }
            $properties = $feature['properties'];
            $id = $properties['unit_id'] ?? null;
            if (!is_string($id) || !isset($expected[$id]) || isset($seen[$id])) {
                throw new InvalidArgumentException('GIS conversion lost or duplicated canonical identities');
            }
            $seen[$id] = true;
            foreach ($expected[$id]['properties'] as $key => $value) {
                if (!array_key_exists($key, $properties) || $properties[$key] !== $value) {
                    throw new InvalidArgumentException('GIS conversion changed ' . $key . ' for ' . $id);
                }
            }
            $before = $this->summarize($expected[$id]['geometry']);
            $after = $this->summarize($feature['geometry']);
            if ($before['polygon_count'] !== $after['polygon_count']
                || $before['ring_count'] !== $after['ring_count']) {
                throw new InvalidArgumentException('GIS conversion changed polygon or hole count for ' . $id);
            }
            for ($i = 0; $i < 4; $i++) {
                if (abs($before['bounds'][$i] - $after['bounds'][$i]) > 1.0e-6) {
                    throw new InvalidArgumentException('GIS conversion changed spatial extent for ' . $id);
                }
            }
            $tolerance = max(1.0e-10, abs($before['area']) * 0.00001);
            if (abs($before['area'] - $after['area']) > $tolerance) {
                throw new InvalidArgumentException('GIS conversion changed polygon area for ' . $id);
            }
        }
    }

    /**
     * @param array<string, mixed> $geometry
     * @return array{polygon_count:int, ring_count:int, bounds:array<int, float>, area:float}
     */
    private function summarize(array $geometry): array
    {
        GeometryContract::assertGeometry($geometry);
        /** @var array<int, array<int, array<int, array<int, int|float>>>> $polygons */
        $polygons = $geometry['type'] === 'Polygon'
            ? [$geometry['coordinates']]
            : $geometry['coordinates'];
        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;
        $ringCount = 0;
        $area = 0.0;
        foreach ($polygons as $polygon) {
            foreach ($polygon as $ringIndex => $ring) {
                $ringCount++;
                $signed = 0.0;
                for ($i = 0, $last = count($ring) - 1; $i < $last; $i++) {
                    $a = $ring[$i];
                    $b = $ring[$i + 1];
                    $signed += $a[0] * $b[1] - $b[0] * $a[1];
                }
                $area += ($ringIndex === 0 ? 1 : -1) * abs($signed / 2);
                foreach ($ring as $point) {
                    $minX = min($minX, (float) $point[0]);
                    $minY = min($minY, (float) $point[1]);
                    $maxX = max($maxX, (float) $point[0]);
                    $maxY = max($maxY, (float) $point[1]);
                }
            }
        }

        return [
            'polygon_count' => count($polygons),
            'ring_count' => $ringCount,
            'bounds' => [$minX, $minY, $maxX, $maxY],
            'area' => $area,
        ];
    }
}
