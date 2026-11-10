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
            ['valid' => true, 'total_units' => 80000],
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
        $report = (new ReleaseEvidence())->aggregate(['valid' => true, 'total_units' => 2], null, null);
        self::assertSame('unverified', $report['checks']['feature_topology']);
        self::assertSame('unverified', $report['checks']['input_parish_matching']);
    }

    /** @test */
    public function itRejectsImpossibleTopologyCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReleaseEvidence())->aggregate(
            ['valid' => true, 'total_units' => 2],
            ['feature_count' => 1, 'failed_count' => 3],
            null
        );
    }
}
