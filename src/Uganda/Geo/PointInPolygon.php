<?php

declare(strict_types=1);

namespace Uganda\Geo;

final class PointInPolygon
{
    /**
     * Classify a WGS84 point relative to GeoJSON Polygon or MultiPolygon geometry.
     *
     * @param array<string, mixed> $geometry
     * @return string inside, outside or boundary
     */
    public function classify(float $longitude, float $latitude, array $geometry): string
    {
        GeometryContract::assertLonLat([$longitude, $latitude]);
        GeometryContract::assertGeometry($geometry);

        /** @var array<int, array<int, array<int, array<int, int|float>>>> $polygons */
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $boundary = false;

        foreach ($polygons as $rings) {
            $shell = $this->inRing($longitude, $latitude, $rings[0]);
            if ($shell === 'outside') {
                continue;
            }
            if ($shell === 'boundary') {
                $boundary = true;
                continue;
            }

            $inHole = false;
            foreach (array_slice($rings, 1) as $hole) {
                $state = $this->inRing($longitude, $latitude, $hole);
                if ($state === 'boundary') {
                    $boundary = true;
                    $inHole = true;
                    break;
                }
                if ($state === 'inside') {
                    $inHole = true;
                    break;
                }
            }

            if (!$inHole) {
                return 'inside';
            }
        }

        return $boundary ? 'boundary' : 'outside';
    }

    /**
     * @param array<int, array<int, float|int>> $ring
     */
    private function inRing(float $longitude, float $latitude, array $ring): string
    {
        $inside = false;
        $edges = count($ring) - 1;
        for ($i = 0, $j = $edges - 1; $i < $edges; $j = $i++) {
            $a = $ring[$j];
            $b = $ring[$i];
            $cross = ((float) $b[0] - (float) $a[0]) * ($latitude - (float) $a[1])
                - ((float) $b[1] - (float) $a[1]) * ($longitude - (float) $a[0]);
            if (abs($cross) <= 1.0e-11
                && $longitude >= min($a[0], $b[0]) - 1.0e-11
                && $longitude <= max($a[0], $b[0]) + 1.0e-11
                && $latitude >= min($a[1], $b[1]) - 1.0e-11
                && $latitude <= max($a[1], $b[1]) + 1.0e-11) {
                return 'boundary';
            }

            if (($a[1] > $latitude) !== ($b[1] > $latitude)
                && $longitude < ($b[0] - $a[0]) * ($latitude - $a[1]) / ($b[1] - $a[1]) + $a[0]) {
                $inside = !$inside;
            }
        }

        return $inside ? 'inside' : 'outside';
    }
}
