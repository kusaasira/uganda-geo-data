<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Geo\ParishGeometryCoverageAuditor;

final class ParishGeometryCoverageAuditorTest extends TestCase
{
    /** @test */
    public function distinguishesReviewedUnresolvedAndUnmatchedParishPolygons(): void
    {
        $features = [
            101 => ['type' => 'Feature'], 102 => ['type' => 'Feature'], 103 => ['type' => 'Feature'],
        ];
        $snapshot = ['units' => [
            ['id' => 'parish:1', 'name' => 'Example', 'parent_id' => 'subcounty:1', 'level' => 'parish'],
            ['id' => 'parish:2', 'name' => 'Same Name', 'parent_id' => 'subcounty:1', 'level' => 'parish'],
        ]];
        $decisions = [['object_id' => 101, 'unit_id' => 'parish:1',
            'parent_unit_id' => 'subcounty:1',
            'evidence_url' => 'https://example.org/official',
            'evidence_date' => '2024-05-10', 'reviewer' => 'GIS reviewer']];

        $report = (new ParishGeometryCoverageAuditor())->audit($features, $snapshot, $decisions, ['102']);

        $this->assertSame(1, $report['verified_count']);
        $this->assertSame(['102'], $report['unresolved_source_object_ids']);
        $this->assertSame(['103'], $report['unmatched_source_object_ids']);
        $this->assertSame(['parish:2'], $report['unmapped_canonical_unit_ids']);
        $this->assertSame('incomplete', $report['coverage']);
    }

    /** @test */
    public function rejectsUnresolvedIdsThatAreAlreadyVerified(): void
    {
        $features = [101 => ['type' => 'Feature']];
        $snapshot = ['units' => [
            ['id' => 'parish:1', 'name' => 'Example', 'parent_id' => 'subcounty:1', 'level' => 'parish'],
        ]];
        $decisions = [['object_id' => 101, 'unit_id' => 'parish:1',
            'parent_unit_id' => 'subcounty:1', 'evidence_url' => 'https://example.org/source',
            'evidence_date' => '2024-05-10', 'reviewer' => 'GIS reviewer']];

        $this->expectException(InvalidArgumentException::class);
        (new ParishGeometryCoverageAuditor())->audit($features, $snapshot, $decisions, ['101']);
    }

    /** @test */
    public function requiresAtLeastOneActualPolygonAndUnitForFullCoverage(): void
    {
        $report = (new ParishGeometryCoverageAuditor())->audit([], ['units' => []], []);
        $this->assertSame('incomplete', $report['coverage']);
    }
    /** @test */
    public function rejectsAssociativeUnresolvedInventoriesThatHideSourceOrder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('indexed list');
        (new ParishGeometryCoverageAuditor())->audit(
            [101 => ['type' => 'Feature']],
            ['units' => []],
            [],
            ['source' => '101']
        );
    }
}
