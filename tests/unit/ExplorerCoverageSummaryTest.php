<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\ExplorerCoverageSummary;

final class ExplorerCoverageSummaryTest extends TestCase
{
    /** @test */
    public function itOnlyExposesSafeAggregateCounts(): void
    {
        $index = ['units' => [
            ['id' => 'p1', 'level' => 'parish'],
            ['id' => 'p2', 'level' => 'parish'],
            ['id' => 'd1', 'level' => 'district'],
        ]];
        $report = [
            'canonical_parish_ward_count' => 2,
            'verified_count' => 1,
            'verified_matches' => [['unit_id' => 'p1', 'reviewer' => 'private name']],
            'unmapped_canonical_unit_ids' => ['p2'],
            'coverage' => 'incomplete',
        ];
        $result = (new ExplorerCoverageSummary())->summarize($index, $report);
        self::assertSame(1, $result['reviewed']);
        self::assertSame(2, $result['total']);
        self::assertSame('unverified', $result['national_coverage']);
        self::assertArrayNotHasKey('verified_matches', $result);
        self::assertArrayNotHasKey('reviewer', $result);
    }

    /** @test */
    public function itRejectsUnmatchedCanonicalIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExplorerCoverageSummary())->summarize(
            ['units' => [['id' => 'p1', 'level' => 'parish']]],
            ['canonical_parish_ward_count' => 1, 'verified_count' => 0,
                'verified_matches' => [], 'unmapped_canonical_unit_ids' => ['wrong'],
                'coverage' => 'incomplete']
        );
    }
}
