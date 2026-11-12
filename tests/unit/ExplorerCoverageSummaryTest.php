<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\ExplorerCoverageSummary;

final class ExplorerCoverageSummaryTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
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
            'verified_matches' => [['unit_id' => 'p1', 'source_object_id' => '10', 'reviewer' => 'private name']],
            'source_feature_count' => 2,
            'unmatched_source_object_ids' => ['11'],
            'unresolved_source_object_ids' => [],
            'unmapped_canonical_unit_ids' => ['p2'],
            'coverage' => 'incomplete',
        ];
        $result = (new ExplorerCoverageSummary())->summarize($index, $report);
        self::assertSame(1, $result['reviewed']);
        self::assertSame(2, $result['total']);
        self::assertSame('unverified', $result['national_coverage']);
        self::assertSame(1, $result['unmatched_source_features']);
        self::assertArrayNotHasKey('verified_matches', $result);
        self::assertArrayNotHasKey('reviewer', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
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
