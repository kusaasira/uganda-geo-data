<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnicodePerformanceBudgetTest extends TestCase
{
    #[Test]
    public function a_measured_fast_fuzzy_query_satisfies_realistic_budgets(): void
    {
        $this->assertProfile(0, []);
    }

    #[Test]
    public function a_significant_warm_fuzzy_regression_fails(): void
    {
        $this->assertProfile(1, ['warm_p95_ms' => 75.01, 'warm_p99_ms' => 85.0]);
    }

    #[Test]
    public function a_significant_cold_fuzzy_regression_fails(): void
    {
        $this->assertProfile(1, ['first_query_ms' => 1500.01]);
    }

    #[Test]
    public function excess_unicode_index_reservation_fails(): void
    {
        $this->assertProfile(1, ['memory_peak_reserved_bytes' => 134217729]);
    }

    #[Test]
    public function fabricated_or_incomplete_measurements_fail_validation(): void
    {
        $this->assertProfile(2, ['fingerprint' => 'incorrect']);
        $this->assertProfile(2, ['warm_p95_ms' => -1]);
        $this->assertProfile(2, ['iterations' => 0]);
        $this->assertProfile(2, ['warm_p50_ms' => 20, 'warm_p95_ms' => 10]);
    }

    #[Test]
    public function a_zero_result_profile_cannot_silently_pass(): void
    {
        $this->assertProfile(1, ['first_result_count' => 0]);
    }

    /** @param array<string, mixed> $changes */
    private function assertProfile(int $expectedExit, array $changes): void
    {
        $baseline = [
            'scenario' => 'fuzzy_unicode_global',
            'iterations' => 20,
            'first_result_count' => 1,
            'fingerprint' => str_repeat('a', 64),
            'first_query_ms' => 250.0,
            'warm_p50_ms' => 8.0,
            'warm_p95_ms' => 19.0,
            'warm_p99_ms' => 21.0,
            'warm_mean_ms' => 9.0,
            'memory_peak_reserved_bytes' => 84000000,
        ];
        $file = tempnam(sys_get_temp_dir(), 'geo-unicode-budget-');
        self::assertNotFalse($file);

        try {
            file_put_contents($file, json_encode(array_replace($baseline, $changes), JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg(dirname(__DIR__, 2) . '/tools/benchmarks/verify-unicode-budgets.php') . ' '
                . escapeshellarg($file) . ' 2>&1';
            $output = [];
            $status = 0;
            exec($command, $output, $status);
            self::assertSame($expectedExit, $status, implode("\n", $output));
        } finally {
            unlink($file);
        }
    }
}
