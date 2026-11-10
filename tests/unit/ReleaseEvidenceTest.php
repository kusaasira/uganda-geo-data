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
            ['feature_count' => 5, 'failed_count' => 0],
            ['coverage' => 'complete', 'verified_count' => 5, 'canonical_parish_ward_count' => 5]
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
