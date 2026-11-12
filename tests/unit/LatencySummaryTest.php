<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\LatencySummary;

final class LatencySummaryTest extends TestCase
{
    /** @test */
    public function itSummarizesUnsortedLatencyMeasurementsDeterministically(): void
    {
        $summary = (new LatencySummary())->summarize([9, 1, 3, 2, 5]);

        self::assertSame(5, $summary['count']);
        self::assertSame(1.0, $summary['min']);
        self::assertSame(3.0, $summary['p50']);
        self::assertSame(9.0, $summary['p95']);
        self::assertSame(9.0, $summary['p99']);
        self::assertSame(9.0, $summary['max']);
        self::assertSame(4.0, $summary['mean']);
    }

    /** @test */
    public function aSingleSampleProducesTheSameValueForEveryPercentile(): void
    {
        $summary = (new LatencySummary())->summarize([0.25]);

        self::assertSame(0.25, $summary['p50']);
        self::assertSame(0.25, $summary['p95']);
        self::assertSame(0.25, $summary['p99']);
    }

    /** @test */
    public function itRejectsEmptySamples(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LatencySummary())->summarize([]);
    }

    /** @test */
    public function itRejectsNegativeSamples(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LatencySummary())->summarize([1, -0.1]);
    }

    /** @test */
    public function itRejectsNonFiniteSamples(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LatencySummary())->summarize([INF]);
    }
}
