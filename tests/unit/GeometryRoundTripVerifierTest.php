<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\GeometryExportFields;
use Uganda\Geo\GeometryRoundTripVerifier;

final class GeometryRoundTripVerifierTest extends TestCase
{
    /** @return array<string, mixed> */
    private function reviewed(): array
    {
        $source = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/fixtures/verified-parish.geojson'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return (new GeometryExportFields())->prepare($source);
    }

    /** @test */
    public function itAcceptsPolygonPromotionWhenAttributesAndGeometryArePreserved(): void
    {
        $source = $this->reviewed();
        $roundTrip = $source;
        $roundTrip['features'][0]['geometry'] = [
            'type' => 'MultiPolygon',
            'coordinates' => [$source['features'][0]['geometry']['coordinates']],
        ];
        (new GeometryRoundTripVerifier())->verify($source, $roundTrip);
        self::assertTrue(true);
    }

    /** @test */
    public function itRejectsModifiedParentIdentifiersEvenWhenUnitIdsMatch(): void
    {
        $source = $this->reviewed();
        $roundTrip = $source;
        $roundTrip['features'][0]['properties']['parent_id'] = 'fixture:subcounty:99';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('parent_id');
        (new GeometryRoundTripVerifier())->verify($source, $roundTrip);
    }

    /** @test */
    public function itRejectsChangedProvenanceDates(): void
    {
        $source = $this->reviewed();
        $roundTrip = $source;
        $roundTrip['features'][0]['properties']['evid_date'] = '2024-06-01';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('evid_date');
        (new GeometryRoundTripVerifier())->verify($source, $roundTrip);
    }

    /** @test */
    public function itRejectsMovedPolygonsThatKeepTheSameIdentity(): void
    {
        $source = $this->reviewed();
        $roundTrip = $source;
        foreach ($roundTrip['features'][0]['geometry']['coordinates'][0] as &$point) {
            $point[0] += 0.01;
        }
        unset($point);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('spatial extent');
        (new GeometryRoundTripVerifier())->verify($source, $roundTrip);
    }

    /** @test */
    public function itRejectsLostInteriorRings(): void
    {
        $source = $this->reviewed();
        $source['features'][0]['geometry']['coordinates'][] = [
            [32.002, 0.002],
            [32.004, 0.002],
            [32.004, 0.004],
            [32.002, 0.004],
            [32.002, 0.002],
        ];
        $roundTrip = $source;
        array_pop($roundTrip['features'][0]['geometry']['coordinates']);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('hole count');
        (new GeometryRoundTripVerifier())->verify($source, $roundTrip);
    }
    /** @test */
    public function itRejectsRelocatedHolesEvenWhenOverallAreaAndExtentAreUnchanged(): void
    {
        $source = $this->reviewed();
        $source['features'][0]['geometry']['coordinates'][] = [
            [32.002, 0.002],
            [32.004, 0.002],
            [32.004, 0.004],
            [32.002, 0.004],
            [32.002, 0.002],
        ];
        $roundTrip = $source;
        foreach ($roundTrip['features'][0]['geometry']['coordinates'][1] as &$point) {
            $point[0] += 0.001;
        }
        unset($point);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('polygon edges');
        (new GeometryRoundTripVerifier())->verify($source, $roundTrip);
    }

    /** @test */
    public function itAcceptsDriverChangesToPolygonRingDirection(): void
    {
        $source = $this->reviewed();
        $roundTrip = $source;
        $roundTrip['features'][0]['geometry']['coordinates'][0] = array_reverse(
            $roundTrip['features'][0]['geometry']['coordinates'][0]
        );
        (new GeometryRoundTripVerifier())->verify($source, $roundTrip);
        self::assertTrue(true);
    }
}
