<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\ArcGisFeatureImporter;

final class ArcGisFeatureImporterTest extends TestCase
{
    /** @return array<string, mixed> */
    private function feature(int $id): array
    {
        return [
            'type' => 'Feature',
            'properties' => ['FID' => $id, 'pname2024' => 'Sample'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[32, 1], [33, 1], [33, 2], [32, 1]]]],
        ];
    }

    /** @test */
    public function preservesFeaturesByObjectIdEvenWhenNamesRepeat(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$this->feature(1), $this->feature(2)]], JSON_THROW_ON_ERROR);
        $features = (new ArcGisFeatureImporter())->parsePage($page, 'FID');
        $this->assertCount(2, $features);
        $this->assertArrayHasKey(1, $features);
        $this->assertArrayHasKey(2, $features);
    }

    /** @test */
    public function rejectsDuplicateObjectIds(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$this->feature(1), $this->feature(1)]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }

    /** @test */
    public function rejectsTruncatedFeatureCollections(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'exceededTransferLimit' => true, 'features' => [$this->feature(1)]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }

    /** @test */
    public function rejectsMalformedPolygonCoordinates(): void
    {
        $feature = $this->feature(1);
        $feature['geometry']['coordinates'][0][0] = [999, 1];
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$feature]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }
}
