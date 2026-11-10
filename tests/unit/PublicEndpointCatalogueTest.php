<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class PublicEndpointCatalogueTest extends TestCase
{
    /** @test */
    public function candidateLayersHaveStableIdentifiersAndConservativeRights(): void
    {
        $catalogue = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/resources/geometry/endpoints.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $catalogue['schema_version']);
        $this->assertNotEmpty($catalogue['endpoints']);
        $seen = [];
        foreach ($catalogue['endpoints'] as $entry) {
            $this->assertNotContains($entry['id'], $seen);
            $seen[] = $entry['id'];
            $this->assertSame('https', parse_url($entry['url'], PHP_URL_SCHEME));
            $this->assertSame('unverified', $entry['redistribution_status']);
            $this->assertSame('EPSG:4326', $entry['requested_crs']);
            $this->assertGreaterThan(0, $entry['max_record_count']);
        }
    }
    /** @test */
    public function rejectsLayerMetadataThatChangesObjectIdOrRequiredFields(): void
    {
        $metadata = [
            'id' => 1,
            'name' => 'Uganda_2024_Parishes',
            'geometryType' => 'esriGeometryPolygon',
            'objectIdField' => 'OBJECTID',
            'maxRecordCount' => 2000,
            'extent' => ['spatialReference' => ['wkid' => 102100]],
            'fields' => [['name' => 'FID']],
        ];
        $catalogue = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/resources/geometry/endpoints.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->expectException(\RuntimeException::class);
        \Uganda\Geo\ArcGisLayerMetadata::assertMatches($metadata, $catalogue['endpoints'][0]);
    }
}
