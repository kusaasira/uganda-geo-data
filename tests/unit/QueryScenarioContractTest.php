<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QueryScenarioContractTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsUnknownQueryScenariosWithoutLoadingDatasets(): void
    {
        $this->assertInvalid('unknown', '100');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsAnUnboundedIterationCountBeforeLoadingDatasets(): void
    {
        $this->assertInvalid('village_id', '100001');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsNonnumericIterationsBeforeLoadingDatasets(): void
    {
        $this->assertInvalid('district_id', 'any');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itProducesMachineReadableResultsForAnActualSourceCheckout(): void
    {
        $root = dirname(__DIR__, 2);
        $output = [];
        $code = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/tools/benchmarks/query-scenario.php') . ' '
            . escapeshellarg($root) . ' district_id 100 2>&1',
            $output,
            $code
        );
        self::assertSame(0, $code, implode("\n", $output));
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('district_id', $result['scenario']);
        self::assertSame(100, $result['samples']);
        self::assertSame(64, strlen($result['fingerprint']));
    }

    private function assertInvalid(string $scenario, string $iterations): void
    {
        $root = dirname(__DIR__, 2);
        $output = [];
        $code = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/tools/benchmarks/query-scenario.php') . ' '
            . escapeshellarg($root) . ' '
            . escapeshellarg($scenario) . ' '
            . escapeshellarg($iterations) . ' 2>&1',
            $output,
            $code
        );
        self::assertSame(2, $code);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }
}
