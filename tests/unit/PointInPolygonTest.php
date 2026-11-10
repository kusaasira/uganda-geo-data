<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\PointInPolygon;

final class PointInPolygonTest extends TestCase
{
    /** @return array<string, mixed> */
    private function geometry(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [
            [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]],
            [[1, 1], [1, 2], [2, 2], [2, 1], [1, 1]],
        ]];
    }

    /** @test */
    public function distinguishesInteriorsExteriorsBoundariesAndHoles(): void
    {
        $lookup = new PointInPolygon();
        $geometry = $this->geometry();
        $this->assertSame('inside', $lookup->classify(3.0, 3.0, $geometry));
        $this->assertSame('outside', $lookup->classify(8.0, 8.0, $geometry));
        $this->assertSame('outside', $lookup->classify(1.5, 1.5, $geometry));
        $this->assertSame('boundary', $lookup->classify(0.0, 2.0, $geometry));
        $this->assertSame('boundary', $lookup->classify(1.0, 1.5, $geometry));
    }

    /** @test */
    public function includesDistinctPartsOfAMultipolygon(): void
    {
        $geometry = ['type' => 'MultiPolygon', 'coordinates' => [
            [[[0, 0], [2, 0], [2, 2], [0, 2], [0, 0]]],
            [[[3, 0], [5, 0], [5, 2], [3, 2], [3, 0]]],
        ]];
        $this->assertSame('inside', (new PointInPolygon())->classify(4.0, 1.0, $geometry));
        $this->assertSame('outside', (new PointInPolygon())->classify(2.5, 1.0, $geometry));
    }

    /** @test */
    public function rejectsInvalidLatitudesInsteadOfGuessing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PointInPolygon())->classify(32.0, 120.0, $this->geometry());
    }
}
