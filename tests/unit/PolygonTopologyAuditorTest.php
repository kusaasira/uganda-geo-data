<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Geo\PolygonTopologyAuditor;

final class PolygonTopologyAuditorTest extends TestCase
{
    /** @test */
    public function acceptsAValidPolygonWithAnInteriorHole(): void
    {
        $geometry = ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
            [[1, 1], [1, 2], [2, 2], [2, 1], [1, 1]],
        ]];
        $result = (new PolygonTopologyAuditor())->audit($geometry);
        $this->assertSame('passed', $result['status']);
        $this->assertCount(2, $result['ring_report']);
    }

    /** @test */
    public function flagsSelfCrossingBowTiePolygons(): void
    {
        $geometry = ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [2, 2], [0, 2], [2, 0], [0, 0]],
        ]];
        $issues = (new PolygonTopologyAuditor())->audit($geometry)['issues'];
        $codes = array_column($issues, 'code');
        $this->assertContains('self_intersection', $codes);
        $this->assertContains('zero_area', $codes);
    }

    /** @test */
    public function flagsHolesWhoseStartingPointLiesOutsideTheShell(): void
    {
        $geometry = ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]],
            [[3, 3], [4, 3], [4, 4], [3, 4], [3, 3]],
        ]];
        $codes = array_column((new PolygonTopologyAuditor())->audit($geometry)['issues'], 'code');
        $this->assertContains('hole_outside_exterior', $codes);
    }

    /** @test */
    public function includesEveryPartOfAMultipolygon(): void
    {
        $one = [[0, 0], [2, 0], [2, 2], [0, 0]];
        $two = [[4, 0], [6, 0], [6, 2], [4, 0]];
        $result = (new PolygonTopologyAuditor())->audit(['type' => 'MultiPolygon', 'coordinates' => [[$one], [$two]]]);
        $this->assertSame(2, $result['polygon_count']);
        $this->assertSame('passed', $result['status']);
    }
}
