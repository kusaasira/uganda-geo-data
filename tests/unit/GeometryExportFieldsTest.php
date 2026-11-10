<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\GeometryExportFields;

final class GeometryExportFieldsTest extends TestCase
{
    /** @test */
    public function itPreservesIdentitiesWithShortDeterministicFieldNames(): void
    {
        $collection = json_decode((string) file_get_contents(
            dirname(__DIR__) . '/fixtures/verified-parish.geojson'
        ), true, 512, JSON_THROW_ON_ERROR);
        $prepared = (new GeometryExportFields())->prepare($collection);
        self::assertCount(1, $prepared['features']);
        self::assertSame('fixture:parish:1', $prepared['features'][0]['properties']['unit_id']);
        self::assertSame('fixture:subcounty:1', $prepared['features'][0]['properties']['parent_id']);
        foreach (array_keys($prepared['features'][0]['properties']) as $field) {
            self::assertLessThanOrEqual(10, strlen($field));
        }
    }

    /** @test */
    public function itRejectsUnreviewedFeatureCollections(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GeometryExportFields())->prepare(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => [], 'geometry' => []],
        ]]);
    }
}
