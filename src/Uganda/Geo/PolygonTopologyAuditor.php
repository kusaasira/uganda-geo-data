<?php

declare(strict_types=1);

namespace Uganda\Geo;

final class PolygonTopologyAuditor
{
    /**
     * Report feature-level topology defects without changing the original geometry.
     *
     * @param array<string, mixed> $geometry
     * @return array<string, mixed>
     */
    public function audit(array $geometry): array
    {
        GeometryContract::assertGeometry($geometry);
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $issues = [];
        $ringReport = [];
        foreach ($polygons as $polygonIndex => $rings) {
            foreach ($rings as $ringIndex => $ring) {
                $area = $this->signedArea($ring);
                $ringReport[] = [
                    'polygon' => $polygonIndex,
                    'ring' => $ringIndex,
                    'signed_area_degrees_squared' => $area,
                    'orientation' => $area > 0 ? 'counterclockwise' : ($area < 0 ? 'clockwise' : 'degenerate'),
                ];
                if (abs($area) < 1.0e-12) {
                    $issues[] = ['polygon' => $polygonIndex, 'ring' => $ringIndex, 'code' => 'zero_area'];
                }
                if ($this->selfIntersects($ring)) {
                    $issues[] = ['polygon' => $polygonIndex, 'ring' => $ringIndex, 'code' => 'self_intersection'];
                }
                if ($ringIndex > 0 && !$this->contains($rings[0], $ring[0])) {
                    $issues[] = ['polygon' => $polygonIndex, 'ring' => $ringIndex, 'code' => 'hole_outside_exterior'];
                }
            }
        }

        return [
            'status' => $issues === [] ? 'passed' : 'failed',
            'polygon_count' => count($polygons),
            'ring_report' => $ringReport,
            'issues' => $issues,
            'limitations' => 'Planar diagnostic in longitude/latitude degrees, not a survey-grade area calculation or sibling overlap/gap audit.',
        ];
    }

    /** @param array<int, array<int, float|int>> $ring */
    private function signedArea(array $ring): float
    {
        $sum = 0.0;
        for ($i = 0, $last = count($ring) - 1; $i < $last; $i++) {
            $a = $ring[$i];
            $b = $ring[$i + 1];
            $sum += (float) $a[0] * (float) $b[1] - (float) $b[0] * (float) $a[1];
        }

        return $sum / 2.0;
    }

    /** @param array<int, array<int, float|int>> $ring */
    private function selfIntersects(array $ring): bool
    {
        $edges = count($ring) - 1;
        for ($i = 0; $i < $edges; $i++) {
            for ($j = $i + 1; $j < $edges; $j++) {
                if ($j === $i + 1 || ($i === 0 && $j === $edges - 1)) {
                    continue;
                }
                if ($this->segmentsIntersect($ring[$i], $ring[$i + 1], $ring[$j], $ring[$j + 1])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int, float|int> $a
     *  @param array<int, float|int> $b
     *  @param array<int, float|int> $c
     */
    private function cross(array $a, array $b, array $c): float
    {
        return ((float) $b[0] - (float) $a[0]) * ((float) $c[1] - (float) $a[1])
            - ((float) $b[1] - (float) $a[1]) * ((float) $c[0] - (float) $a[0]);
    }

    /** @param array<int, float|int> $a
     *  @param array<int, float|int> $b
     *  @param array<int, float|int> $point
     */
    private function onSegment(array $a, array $b, array $point): bool
    {
        $epsilon = 1.0e-12;
        return abs($this->cross($a, $b, $point)) <= $epsilon
            && $point[0] >= min($a[0], $b[0]) - $epsilon
            && $point[0] <= max($a[0], $b[0]) + $epsilon
            && $point[1] >= min($a[1], $b[1]) - $epsilon
            && $point[1] <= max($a[1], $b[1]) + $epsilon;
    }

    /** @param array<int, float|int> $a
     *  @param array<int, float|int> $b
     *  @param array<int, float|int> $c
     *  @param array<int, float|int> $d
     */
    private function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $abC = $this->cross($a, $b, $c);
        $abD = $this->cross($a, $b, $d);
        $cdA = $this->cross($c, $d, $a);
        $cdB = $this->cross($c, $d, $b);
        if (($abC > 0 && $abD < 0 || $abC < 0 && $abD > 0)
            && ($cdA > 0 && $cdB < 0 || $cdA < 0 && $cdB > 0)) {
            return true;
        }

        return ($this->onSegment($a, $b, $c) || $this->onSegment($a, $b, $d)
            || $this->onSegment($c, $d, $a) || $this->onSegment($c, $d, $b));
    }

    /** @param array<int, array<int, float|int>> $ring
     *  @param array<int, float|int> $point
     */
    private function contains(array $ring, array $point): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 2; $i < count($ring) - 1; $j = $i++) {
            if ($this->onSegment($ring[$j], $ring[$i], $point)) {
                return false;
            }
            $a = $ring[$i];
            $b = $ring[$j];
            if (($a[1] > $point[1]) !== ($b[1] > $point[1])
                && $point[0] < ($b[0] - $a[0]) * ($point[1] - $a[1]) / ($b[1] - $a[1]) + $a[0]) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
