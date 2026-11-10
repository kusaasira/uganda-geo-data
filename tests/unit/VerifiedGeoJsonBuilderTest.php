<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\ParishBoundaryMatcher;
use Uganda\Geo\VerifiedGeoJsonBuilder;

final class VerifiedGeoJsonBuilderTest extends TestCase
{
    /** @test */
    public function exportsOnlyReviewedPolygonsWithStableCanonicalIdentities(): void
    {
        $geometry = ['type' => 'Polygon', 'coordinates' => [
            [[32, 0], [33, 0], [33, 1], [32, 0]],
        ]];
        $features = [11 => ['geometry' => $geometry], 12 => ['geometry' => $geometry]];
        $snapshot = ['units' => [
            ['id' => 'parish:2', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:2'],
        ]];
        $decisions = [[
            'object_id' => 12, 'unit_id' => 'parish:2', 'parent_unit_id' => 'subcounty:2',
            'evidence_url' => 'https://www.ubos.org/source', 'evidence_date' => '2024-11-01',
            'reviewer' => 'GIS reviewer',
        ]];
        $report = (new ParishBoundaryMatcher())->match($features, $snapshot, $decisions);
        $result = (new VerifiedGeoJsonBuilder())->build($features, $report, 'public-parish-service');
        $this->assertSame('FeatureCollection', $result['type']);
        $this->assertCount(1, $result['features']);
        $this->assertSame('parish:2', $result['features'][0]['id']);
        $this->assertSame('12', $result['features'][0]['properties']['source_object_id']);
        $this->assertSame('incomplete', $result['metadata']['coverage_status']);
        $this->assertSame('review_required', $result['metadata']['publication_rights']);
    }

    /** @test */
    public function refusesToPublishAReviewWithoutAnActualPolygon(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new VerifiedGeoJsonBuilder())->build([], ['matches' => [[
            'source_object_id' => 99, 'unit_id' => 'parish:99', 'parent_unit_id' => 'subcounty:1',
            'evidence_url' => 'https://www.ubos.org', 'evidence_date' => '2024-11-01', 'reviewer' => 'r',
        ]]], 'source');
    }

    /** @test */
    public function anEmptyVerifiedSetCannotClaimCompleteSourceCoverage(): void
    {
        $result = (new VerifiedGeoJsonBuilder())->build([], ['matches' => [], 'status' => 'incomplete'], 'source');
        $this->assertSame([], $result['features']);
        $this->assertSame('incomplete', $result['metadata']['coverage_status']);
    }
}
