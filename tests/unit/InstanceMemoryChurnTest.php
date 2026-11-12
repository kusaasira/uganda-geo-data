<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InstanceMemoryChurnTest extends TestCase
{
    #[Test]
    public function separate_engine_instances_release_snapshot_memory_after_gc(): void
    {
        [$status, $output] = $this->run('4');
        self::assertSame(0, $status, $output);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('instance-churn', $data['scenario']);
        self::assertSame(4, $data['iterations']);
        self::assertCount(4, $data['active_samples_after_release_bytes']);
        self::assertLessThanOrEqual(
            4194304,
            $data['retained_growth_bytes'],
            'Freed engine instances should not retain more than 4 MiB'
        );
    }

    #[Test]
    public function invalid_iteration_limits_fail_before_allocating_snapshots(): void
    {
        foreach (['0', '1', '31', 'not-a-number'] as $iterations) {
            [$status, $output] = $this->run($iterations);
            self::assertSame(2, $status, $output);
            self::assertStringContainsString('Usage:', $output);
        }
    }

    /** @return array{0:int,1:string} */
    private function run(string $iterations): array
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/tools/benchmarks/instance-churn.php') . ' '
            . escapeshellarg($iterations) . ' 2>&1';
        $lines = [];
        $status = 0;
        exec($command, $lines, $status);

        return [$status, implode("\n", $lines)];
    }
}
