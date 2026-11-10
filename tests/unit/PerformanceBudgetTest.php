<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\PerformanceBudget;

final class PerformanceBudgetTest extends TestCase
{
    /** @test */
    public function itAllowsMeasurementsWithinTheirBudgets(): void
    {
        self::assertSame([], (new PerformanceBudget())->violations(
            ['constructor_ms' => 290.5, 'index_warmup_ms' => 40, 'memory_peak_bytes' => 1000],
            ['constructor_ms' => 500, 'index_warmup_ms' => 200, 'memory_peak_bytes' => 2000]
        ));
    }

    /** @test */
    public function itReportsEachExceededBudget(): void
    {
        $errors = (new PerformanceBudget())->violations(
            ['constructor_ms' => 900, 'index_warmup_ms' => 40, 'memory_peak_bytes' => 2048],
            ['constructor_ms' => 500, 'index_warmup_ms' => 200, 'memory_peak_bytes' => 1024]
        );
        self::assertCount(2, $errors);
        self::assertStringContainsString('constructor_ms', $errors[0]);
        self::assertStringContainsString('memory_peak_bytes', $errors[1]);
    }

    /** @test */
    public function itRejectsInvalidOrMissingMeasurements(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PerformanceBudget())->violations(
            ['constructor_ms' => 1, 'index_warmup_ms' => 1],
            ['constructor_ms' => 500, 'index_warmup_ms' => 200, 'memory_peak_bytes' => 1024]
        );
    }
}
