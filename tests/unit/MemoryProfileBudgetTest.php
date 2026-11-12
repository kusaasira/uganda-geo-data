<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemoryProfileBudgetTest extends TestCase
{
    #[Test]
    public function valid_profile_passes_the_memory_budget(): void
    {
        $this->assertProfile(0, []);
    }

    #[Test]
    public function memory_peak_over_128_mib_is_rejected(): void
    {
        $this->assertProfile(1, ['memory_peak_reserved_bytes' => 134217729]);
    }

    #[Test]
    public function unbounded_retained_memory_is_rejected(): void
    {
        $this->assertProfile(1, [
            'memory_active_after_second_pass_bytes' => 50388609,
            'memory_peak_active_bytes' => 51000000,
            'retained_growth_second_pass_bytes' => 8388609,
        ]);
    }

    #[Test]
    public function fabricated_growth_does_not_pass_the_budget_verifier(): void
    {
        $this->assertProfile(2, ['retained_growth_second_pass_bytes' => 1]);
    }

    #[Test]
    public function fabricated_allocator_peaks_do_not_pass_validation(): void
    {
        $this->assertProfile(2, ['memory_peak_reserved_bytes' => 1000000]);
    }

    #[Test]
    public function missing_measurements_and_zero_coverage_are_rejected(): void
    {
        $this->assertProfile(2, ['memory_active_after_first_pass_bytes' => null]);
        $this->assertProfile(1, ['records_visited_per_pass' => 0]);
    }

    /** @param array<string, int|null> $overrides */
    private function assertProfile(int $expectedExit, array $overrides): void
    {
        $base = [
            'scenario' => 'cache-churn',
            'memory_active_after_constructor_bytes' => 40000000,
            'memory_active_after_warmup_bytes' => 42000000,
            'memory_active_after_first_pass_bytes' => 42000000,
            'memory_active_after_second_pass_bytes' => 42000000,
            'memory_peak_active_bytes' => 50000000,
            'memory_peak_reserved_bytes' => 70000000,
            'retained_growth_second_pass_bytes' => 0,
            'records_visited_per_pass' => 100,
        ];
        $file = tempnam(sys_get_temp_dir(), 'geo-memory-');
        self::assertNotFalse($file);
        try {
            file_put_contents($file, json_encode(array_replace($base, $overrides), JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg(dirname(__DIR__, 2) . '/tools/benchmarks/verify-memory-profile.php')
                . ' ' . escapeshellarg($file) . ' 2>&1';
            $lines = [];
            $exit = 0;
            exec($command, $lines, $exit);
            self::assertSame($expectedExit, $exit, implode("\n", $lines));
        } finally {
            unlink($file);
        }
    }
}
