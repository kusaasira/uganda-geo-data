<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RelativePerformanceBudgetTest extends TestCase
{
    #[Test]
    public function improved_lookups_are_accepted(): void
    {
        $this->assertBudget(0, []);
    }

    #[Test]
    public function changed_result_identities_are_rejected(): void
    {
        $this->assertBudget(1, ['fingerprint' => 'changed']);
    }

    #[Test]
    public function material_memory_regressions_are_rejected(): void
    {
        $this->assertBudget(1, ['memory_peak_bytes' => 30000000]);
    }

    #[Test]
    public function material_warm_latency_regressions_are_rejected(): void
    {
        $this->assertBudget(1, ['warm_mean_us' => 5.0]);
    }

    #[Test]
    public function material_first_access_regressions_are_rejected(): void
    {
        $this->assertBudget(1, ['first_query_us' => 3000.0]);
    }

    /** @param array<string, int|float|string> $changed */
    private function assertBudget(int $expectedExit, array $changed): void
    {
        $baseline = [
            'scenario' => 'village_id',
            'fingerprint' => 'same',
            'first_query_us' => 100.0,
            'warm_mean_us' => 2.0,
            'warm_p95_us' => 2.5,
            'memory_peak_bytes' => 10000000,
        ];
        $candidate = array_merge($baseline, [
            'first_query_us' => 50.0,
            'warm_mean_us' => 1.0,
            'warm_p95_us' => 1.2,
            'memory_peak_bytes' => 8000000,
        ], $changed);

        $root = dirname(__DIR__, 2);
        $directory = sys_get_temp_dir() . '/uganda-geo-budget-' . bin2hex(random_bytes(6));
        mkdir($directory);
        try {
            file_put_contents($directory . '/base.json', json_encode($baseline, JSON_THROW_ON_ERROR));
            file_put_contents($directory . '/candidate.json', json_encode($candidate, JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($root . '/tools/benchmarks/verify-relative-budgets.php') . ' '
                . escapeshellarg($directory . '/base.json') . ' '
                . escapeshellarg($directory . '/candidate.json') . ' 2>&1';
            $lines = [];
            $exitCode = 0;
            exec($command, $lines, $exitCode);
            self::assertSame($expectedExit, $exitCode, implode("\n", $lines));
        } finally {
            foreach (glob($directory . '/*.json') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
