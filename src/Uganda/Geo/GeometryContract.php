<?php

declare(strict_types=1);

namespace Uganda\Geo;

use InvalidArgumentException;

final class GeometryContract
{
    /**
     * Validate GeoJSON polygon coordinate structure in RFC 7946 WGS84 longitude/latitude order.
     * This is not a topology, overlap or containment validator.
     *
     * @param array<string, mixed> $geometry
     */
    public static function assertGeometry(array $geometry): void
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (!in_array($type, ['Polygon', 'MultiPolygon'], true) || !is_array($coordinates) || $coordinates === []) {
            throw new InvalidArgumentException('Expected a non-empty GeoJSON Polygon or MultiPolygon');
        }

        $polygons = $type === 'Polygon' ? [$coordinates] : $coordinates;
        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || $polygon === []) {
                throw new InvalidArgumentException('Polygon must contain at least one ring');
            }
            foreach ($polygon as $ring) {
                if (!is_array($ring) || count($ring) < 4) {
                    throw new InvalidArgumentException('Polygon ring must contain at least four positions');
                }
                foreach ($ring as $position) {
                    if (!is_array($position)) {
                        throw new InvalidArgumentException('GeoJSON position must be a coordinate array');
                    }
                    self::assertLonLat($position);
                }
                $first = reset($ring);
                $last = end($ring);
                if (!is_array($first) || !is_array($last) || abs($first[0] - $last[0]) > 1.0e-10 || abs($first[1] - $last[1]) > 1.0e-10) {
                    throw new InvalidArgumentException('Polygon ring is not closed');
                }
            }
        }
    }

    /**
     * @param array<int, mixed> $position
     */
    public static function assertLonLat(array $position): void
    {
        if (count($position) < 2 || !isset($position[0], $position[1])) {
            throw new InvalidArgumentException('Position must include longitude and latitude');
        }
        $longitude = $position[0];
        $latitude = $position[1];
        if ((!is_int($longitude) && !is_float($longitude)) || (!is_int($latitude) && !is_float($latitude)) ||
            !is_finite((float) $longitude) || !is_finite((float) $latitude) ||
            $longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException('Position must contain valid EPSG:4326 longitude and latitude');
        }
    }
}
