<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LookupLatencyCliTest extends TestCase
{
    /** @test */
    public function itRejectsAnIterationCountBelowTheSamplingMinimum(): void
    {
        $this->assertInvalidIterations('49');
    }

    /** @test */
    public function itRejectsAnIterationCountAboveTheSamplingMaximum(): void
    {
        $this->assertInvalidIterations('100001');
    }

    /** @test */
    public function itRejectsUnexpectedArguments(): void
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/tools/benchmarks/lookup-latency.php')
            . ' 100 extra 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }

    private function assertInvalidIterations(string $argument): void
    {
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/tools/benchmarks/lookup-latency.php')
            . ' ' . escapeshellarg($argument) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }
}
