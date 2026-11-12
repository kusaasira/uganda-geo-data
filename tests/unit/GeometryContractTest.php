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
    public function rejects_keyed_geojson_coordinate_containers(): void
    {
        $ring = [[32, 1], [33, 1], [33, 2], [32, 1]];
        $cases = [
            ['type' => 'Polygon', 'coordinates' => ['shell' => $ring]],
            ['type' => 'MultiPolygon', 'coordinates' => ['a' => [$ring]]],
            ['type' => 'Polygon', 'coordinates' => [['ring' => $ring]]],
            ['type' => 'Polygon', 'coordinates' => [[
                'a' => [32, 1], 'b' => [33, 1], 'c' => [33, 2], 'd' => [32, 1],
            ]]],
            ['type' => 'Polygon', 'coordinates' => [[
                ['lon' => 32, 'lat' => 1], [33, 1], [33, 2], [32, 1],
            ]]],
        ];
        foreach ($cases as $case => $geometry) {
            try {
                GeometryContract::assertGeometry($geometry);
                self::fail('Accepted malformed keyed GeoJSON array: case ' . $case);
            } catch (InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function permits_numeric_elevation_but_rejects_invalid_additional_ordinates(): void
    {
        GeometryContract::assertLonLat([32, 1, 1142.5]);
        foreach ([[32, 1, 'altitude'], [32, 1, NAN], [32, 1, INF], [32, 1, null]] as $position) {
            try {
                GeometryContract::assertLonLat($position);
                self::fail('A nonnumeric or nonfinite GeoJSON altitude was accepted');
            } catch (InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsNonFiniteCoordinateValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GeometryContract::assertLonLat([NAN, 1]);
    }
}
