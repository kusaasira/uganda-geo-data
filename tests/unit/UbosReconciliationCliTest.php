<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class UbosReconciliationCliTest extends TestCase
{
    /** @test */
    public function generatesAnHonestMachineReadableReferenceReport(): void
    {
        if (!function_exists('exec')) {
            $this->markTestSkipped('PHP process execution is unavailable');
        }
        $root = dirname(__DIR__, 2);
        $command = escapeshellarg(PHP_BINARY) . ' ' .
            escapeshellarg($root . '/tools/data/reconcile-ubos.php') . ' 2>&1';
        $output = [];
        $status = 1;
        exec($command, $output, $status);
        $this->assertSame(0, $status, implode("\n", $output));

        $report = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('aggregate_baseline_only', $report['status']);
        $this->assertSame('legacy-bundled-baseline', $report['snapshot_id']);
        $this->assertCount(2, $report['reports']);
        $this->assertGreaterThan(70000, $report['total_legacy_records']);
        foreach ($report['reports'] as $publication) {
            $this->assertNotEmpty($publication['id']);
            foreach ($publication['comparisons'] as $comparison) {
                $this->assertFalse($comparison['classification_verified']);
                $this->assertFalse($comparison['record_level_match_verified']);
            }
        }
    }
}
