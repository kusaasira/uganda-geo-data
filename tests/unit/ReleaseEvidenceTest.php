<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\ReleaseEvidence;

final class ReleaseEvidenceTest extends TestCase
{
    /** @test */
    public function itNeverInfersPublicationApprovalFromPassingTechnicalChecks(): void
    {
        $report = (new ReleaseEvidence())->aggregate(
            ['valid' => true, 'total_units' => 80000, 'counts_by_level' => ['district' => 79995, 'parish' => 5], 'errors' => []],
            ['feature_count' => 5, 'failed_count' => 0, 'results' => array_map(
                static function (int $index): array {
                    return ['feature_index' => $index, 'audit' => ['status' => 'passed', 'issues' => []]];
                },
                range(0, 4)
            )],
            [
                'coverage' => 'complete',
                'verified_count' => 5,
                'canonical_parish_ward_count' => 5,
                'source_feature_count' => 5,
                'verified_matches' => array_map(static function (int $i): array {
                    return ['unit_id' => 'parish:' . $i, 'source_object_id' => (string) $i];
                }, range(0, 4)),
                'unmapped_canonical_unit_ids' => [],
                'unmatched_source_object_ids' => [],
                'unresolved_source_object_ids' => [],
            ]
        );
        self::assertSame('passed', $report['checks']['feature_topology']);
        self::assertSame('reported_complete', $report['checks']['input_parish_matching']);
        self::assertSame('unverified', $report['checks']['national_boundary_coverage']);
        self::assertSame('unverified', $report['checks']['publication_rights']);
        self::assertFalse($report['release_ready']);
    }

    /** @test */
    public function itPreservesMissingEvidenceAsUnverified(): void
    {
        $report = (new ReleaseEvidence())->aggregate(['valid' => true, 'total_units' => 2, 'counts_by_level' => ['district' => 2], 'errors' => []], null, null);
        self::assertSame('unverified', $report['checks']['feature_topology']);
        self::assertSame('unverified', $report['checks']['input_parish_matching']);
    }

    /** @test */
    public function itRejectsImpossibleTopologyCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseEvidence())->aggregate(
            ['valid' => true, 'total_units' => 2, 'counts_by_level' => ['district' => 2], 'errors' => []],
            ['feature_count' => 1, 'failed_count' => 3],
            null
        );
    }
    /** @test */
    public function itRejectsFakePassingTopologyCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contradicts');
        (new ReleaseEvidence())->aggregate(
            ['valid' => true, 'total_units' => 1, 'counts_by_level' => ['district' => 1], 'errors' => []],
            ['feature_count' => 1, 'failed_count' => 0, 'results' => [
                ['feature_index' => 0, 'audit' => [
                    'status' => 'failed', 'issues' => [['code' => 'self_intersection']],
                ]],
            ]],
            null
        );
    }

    /** @test */
    public function itRejectsCompleteCoverageClaimsWithUnmappedUnits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parish coverage status contradicts matching evidence');
        (new ReleaseEvidence())->aggregate(
            ['valid' => true, 'total_units' => 2, 'counts_by_level' => ['parish' => 2], 'errors' => []],
            null,
            [
                'coverage' => 'complete',
                'verified_count' => 1,
                'canonical_parish_ward_count' => 2,
                'source_feature_count' => 1,
                'verified_matches' => [['unit_id' => 'p1', 'source_object_id' => '10']],
                'unmapped_canonical_unit_ids' => ['p2'],
                'unmatched_source_object_ids' => [],
                'unresolved_source_object_ids' => [],
            ]
        );
    }

    /** @test */
    public function itRejectsDuplicateUnitMappingsDespiteMatchingTotals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('duplicate verified parish');
        (new ReleaseEvidence())->aggregate(
            ['valid' => true, 'total_units' => 2, 'counts_by_level' => ['parish' => 2], 'errors' => []],
            null,
            [
                'coverage' => 'complete',
                'verified_count' => 2,
                'canonical_parish_ward_count' => 2,
                'source_feature_count' => 2,
                'verified_matches' => [
                    ['unit_id' => 'p1', 'source_object_id' => '10'],
                    ['unit_id' => 'p1', 'source_object_id' => '11'],
                ],
                'unmapped_canonical_unit_ids' => [],
                'unmatched_source_object_ids' => [],
                'unresolved_source_object_ids' => [],
            ]
        );
    }
    /** @test */
    public function itRejectsClaimedValidCanonicalEvidenceWithMissingRows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('self-contradictory');
        (new ReleaseEvidence())->aggregate([
            'valid' => true,
            'total_units' => 3,
            'counts_by_level' => ['district' => 2],
            'errors' => [],
        ], null, null);
    }

    /** @test */
    public function itRejectsCanonicalEvidenceWithoutErrorInventory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Incomplete canonical integrity evidence');
        (new ReleaseEvidence())->aggregate([
            'valid' => true,
            'total_units' => 1,
            'counts_by_level' => ['district' => 1],
        ], null, null);
    }
    /** @test */
    public function itRejectsPassingSampleCoverageForAFullCanonicalParishSnapshot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different canonical parish/ward inventory');
        (new ReleaseEvidence())->aggregate(
            [
                'valid' => true,
                'total_units' => 100,
                'counts_by_level' => ['district' => 1, 'parish' => 99],
                'errors' => [],
            ],
            null,
            [
                'coverage' => 'complete',
                'verified_count' => 1,
                'canonical_parish_ward_count' => 1,
                'source_feature_count' => 1,
                'verified_matches' => [['unit_id' => 'parish:1', 'source_object_id' => '1']],
                'unmapped_canonical_unit_ids' => [],
                'unmatched_source_object_ids' => [],
                'unresolved_source_object_ids' => [],
            ]
        );
    }
}
