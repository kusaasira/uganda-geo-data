<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Geo\ParishGeometryReconciler;

final class ParishGeometryReconcilerTest extends TestCase
{
    /** @test */
    public function reportsVerifiedUnresolvedAndUnmatchedPolygonCoverage(): void
    {
        $units = [
            ['id' => 'legacy:parish:1', 'level' => 'parish', 'parent_id' => 'legacy:subcounty:5'],
            ['id' => 'legacy:parish:2', 'level' => 'parish', 'parent_id' => 'legacy:subcounty:5'],
        ];
        $features = [
            101 => ['type' => 'Feature', 'properties' => ['FID' => 101, 'Parish' => 'Example']],
            102 => ['type' => 'Feature', 'properties' => ['FID' => 102, 'Parish' => 'Same Name']],
            103 => ['type' => 'Feature', 'properties' => ['FID' => 103, 'Parish' => 'Same Name']],
        ];
        $decisions = [
            ['source_object_id' => '101', 'canonical_parish_id' => 'legacy:parish:1',
                'canonical_parent_id' => 'legacy:subcounty:5', 'status' => 'verified',
                'evidence_url' => 'https://example.org/official', 'evidence_date' => '2024-05-10'],
            ['source_object_id' => '102', 'canonical_parish_id' => null, 'status' => 'unresolved'],
        ];
        $report = (new ParishGeometryReconciler())->reconcile($units, $features, $decisions);
        $this->assertTrue($report['valid'], implode('; ', $report['errors']));
        $this->assertSame(['101'], $report['verified_source_object_ids']);
        $this->assertSame(['102'], $report['unresolved_source_object_ids']);
        $this->assertSame(['103'], $report['unmatched_source_object_ids']);
        $this->assertSame(['legacy:parish:2'], $report['unmapped_parish_ids']);
        $this->assertSame('incomplete', $report['coverage']);
    }

    /** @test */
    public function rejectsNameOnlyOrIncorrectParentMatches(): void
    {
        $units = [['id' => 'legacy:parish:1', 'level' => 'parish', 'parent_id' => 'legacy:subcounty:5']];
        $features = [101 => ['type' => 'Feature', 'properties' => ['FID' => 101]]];
        $decisions = [
            ['source_object_id' => '101', 'canonical_parish_id' => 'legacy:parish:1',
                'canonical_parent_id' => 'legacy:subcounty:99', 'status' => 'verified',
                'evidence_url' => 'https://example.org/official', 'evidence_date' => '2024-05-10'],
        ];
        $report = (new ParishGeometryReconciler())->reconcile($units, $features, $decisions);
        $this->assertFalse($report['valid']);
        $this->assertNotEmpty($report['errors']);
    }

    /** @test */
    public function refusesSamePolygonAssignedToTwoDifferentParishes(): void
    {
        $units = [['id' => 'legacy:parish:1', 'level' => 'parish', 'parent_id' => 'legacy:subcounty:5']];
        $features = [101 => ['type' => 'Feature', 'properties' => []]];
        $decision = ['source_object_id' => '101', 'canonical_parish_id' => 'legacy:parish:1',
            'canonical_parent_id' => 'legacy:subcounty:5', 'status' => 'verified',
            'evidence_url' => 'https://example.org/official', 'evidence_date' => '2024-05-10'];
        $report = (new ParishGeometryReconciler())->reconcile($units, $features, [$decision, $decision]);
        $this->assertFalse($report['valid']);
    }
}
