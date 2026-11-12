<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\GeometryContract;

final class GeometryContractTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function supportsClosedPolygonsAndMultipolygons(): void
    {
        $ring = [[32, 1], [33, 1], [33, 2], [32, 1]];
        GeometryContract::assertGeometry(['type' => 'Polygon', 'coordinates' => [$ring]]);
        GeometryContract::assertGeometry(['type' => 'MultiPolygon', 'coordinates' => [[$ring], [$ring]]]);
        $this->assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsOpenRings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeometryContract::assertGeometry(['type' => 'Polygon', 'coordinates' => [[[32, 1], [33, 1], [33, 2], [32, 2]]]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsInvalidLongitudeAndLatitude(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeometryContract::assertLonLat([332, 1]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsMissingRingCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeometryContract::assertGeometry(['type' => 'MultiPolygon', 'coordinates' => []]);
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsDegenerateRingsWithOnlyTwoUniquePositions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeometryContract::assertGeometry([
            'type' => 'Polygon',
            'coordinates' => [[[32, 1], [33, 1], [32, 1], [33, 1], [32, 1]]],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsProjectedMetreCoordinatesAsLongitudeLatitude(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeometryContract::assertLonLat([3500000, 400000]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsNonFiniteCoordinateValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeometryContract::assertLonLat([NAN, 1]);
    }
}
