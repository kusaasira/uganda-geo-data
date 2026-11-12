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

    #[\PHPUnit\Framework\Attributes\Test]
    public function preservesFeaturesByObjectIdEvenWhenNamesRepeat(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$this->feature(1), $this->feature(2)]], JSON_THROW_ON_ERROR);
        $features = (new ArcGisFeatureImporter())->parsePage($page, 'FID');
        $this->assertCount(2, $features);
        $this->assertArrayHasKey(1, $features);
        $this->assertArrayHasKey(2, $features);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_a_feature_object_instead_of_a_geojson_feature_array(): void
    {
        $page = json_encode([
            'type' => 'FeatureCollection',
            'features' => ['one' => $this->feature(1)],
        ], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FeatureCollection');
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsDuplicateObjectIds(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$this->feature(1), $this->feature(1)]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsTruncatedFeatureCollections(): void
    {
        $page = json_encode(['type' => 'FeatureCollection', 'exceededTransferLimit' => true, 'features' => [$this->feature(1)]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsMalformedPolygonCoordinates(): void
    {
        $feature = $this->feature(1);
        $feature['geometry']['coordinates'][0][0] = [999, 1];
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$feature]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }
    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsConflictingFeatureAndObjectIdentifiers(): void
    {
        $feature = $this->feature(1);
        $feature['id'] = 2;
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$feature]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conflicting');
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsAFeatureMissingItsObjectIdentifier(): void
    {
        $feature = $this->feature(1);
        unset($feature['properties']['FID']);
        $page = json_encode(['type' => 'FeatureCollection', 'features' => [$feature]], JSON_THROW_ON_ERROR);
        $this->expectException(InvalidArgumentException::class);
        (new ArcGisFeatureImporter())->parsePage($page, 'FID');
    }
}
