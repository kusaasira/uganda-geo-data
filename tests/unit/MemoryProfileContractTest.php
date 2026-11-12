<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemoryProfileContractTest extends TestCase
{
    #[Test]
    public function invalid_workload_or_unbounded_repetitions_fail_before_initialization(): void
    {
        foreach ([['unknown', '3'], ['construct', '0'], ['cache-churn', '2501'], ['stream', '21']] as [$scenario, $iterations]) {
            [$exit, $output] = $this->runProfile($scenario, $iterations);
            self::assertSame(2, $exit, $scenario . ': ' . $output);
            self::assertStringContainsString('Usage:', $output);
        }
    }

    #[Test]
    public function construction_profile_reports_both_allocator_and_active_memory(): void
    {
        [$exit, $output] = $this->runProfile('construct', '1');
        self::assertSame(0, $exit, $output);
        $profile = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('construct', $profile['scenario']);
        self::assertSame(1, $profile['records_visited_per_pass']);
        self::assertGreaterThan(0, $profile['memory_active_after_constructor_bytes']);
        self::assertGreaterThanOrEqual(
            $profile['memory_active_after_constructor_bytes'],
            $profile['memory_peak_active_bytes']
        );
        self::assertGreaterThanOrEqual(
            $profile['memory_peak_active_bytes'],
            $profile['memory_peak_reserved_bytes']
        );
        self::assertGreaterThanOrEqual(0, $profile['retained_growth_second_pass_bytes']);
    }

    /** @return array{0:int,1:string} */
    private function runProfile(string $scenario, string $iterations): array
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/tools/benchmarks/memory-profile.php')
            . ' ' . escapeshellarg($scenario)
            . ' ' . escapeshellarg($iterations)
            . ' 2>&1';
        $lines = [];
        $exit = 0;
        exec($command, $lines, $exit);

        return [$exit, implode("\n", $lines)];
    }
}
