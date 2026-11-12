<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstanceChurnBudgetTest extends TestCase
{
    #[Test]
    public function valid_instance_churn_passes(): void
    {
        $this->assertReport(0, []);
    }

    #[Test]
    public function oversized_retention_fails(): void
    {
        $this->assertReport(1, [
            'active_samples_after_release_bytes' => [1200000, 6500000],
            'active_last_bytes' => 6500000,
            'retained_growth_bytes' => 5500000,
        ]);
    }

    #[Test]
    public function invented_retention_ignoring_actual_samples_is_rejected(): void
    {
        $this->assertReport(2, ['retained_growth_bytes' => 100]);
    }

    #[Test]
    public function missing_samples_are_rejected(): void
    {
        $this->assertReport(2, ['active_samples_after_release_bytes' => []]);
    }

    #[Test]
    public function excessive_peak_reservation_fails(): void
    {
        $this->assertReport(1, ['memory_peak_reserved_bytes' => 134217729]);
    }

    /** @param array<string, mixed> $changes */
    private function assertReport(int $expectedExit, array $changes): void
    {
        $baseline = [
            'scenario' => 'instance-churn',
            'iterations' => 2,
            'active_baseline_bytes' => 1000000,
            'active_samples_after_release_bytes' => [1200000, 1300000],
            'active_last_bytes' => 1300000,
            'retained_growth_bytes' => 300000,
            'memory_peak_reserved_bytes' => 80000000,
        ];
        $path = tempnam(sys_get_temp_dir(), 'geo-churn-report-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, json_encode(array_replace($baseline, $changes), JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg(dirname(__DIR__, 2) . '/tools/benchmarks/verify-instance-churn.php') . ' '
                . escapeshellarg($path) . ' 2>&1';
            $output = [];
            $status = 0;
            exec($command, $output, $status);
            self::assertSame($expectedExit, $status, implode("\n", $output));
        } finally {
            unlink($path);
        }
    }
}
