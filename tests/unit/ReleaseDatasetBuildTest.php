<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseDatasetBuildTest extends TestCase
{
    /** @test */
    public function itBuildsBitForBitIdenticalSnapshotsAndReports(): void
    {
        $root = dirname(__DIR__, 2);
        $base = sys_get_temp_dir() . '/uganda-geo-release-test-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($base, 0700));
        try {
            foreach (['first', 'second'] as $name) {
                $output = [];
                $status = 0;
                exec(escapeshellarg(PHP_BINARY) . ' '
                    . escapeshellarg($root . '/tools/release/build-dataset.php') . ' '
                    . escapeshellarg($root . '/src/Uganda/Data') . ' '
                    . escapeshellarg($base . '/' . $name) . ' 2>&1', $output, $status);
                self::assertSame(0, $status, implode("\n", $output));
            }
            foreach (['canonical.json', 'qa.json', 'manifest.json'] as $file) {
                self::assertSame(
                    hash_file('sha256', $base . '/first/' . $file),
                    hash_file('sha256', $base . '/second/' . $file)
                );
            }
            $report = json_decode(
                (string) file_get_contents($base . '/first/qa.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            self::assertTrue($report['valid']);
            self::assertGreaterThan(0, $report['total_units']);
        } finally {
            foreach (['first', 'second'] as $name) {
                foreach (['canonical.json', 'qa.json', 'manifest.json'] as $file) {
                    if (is_file($base . '/' . $name . '/' . $file)) {
                        unlink($base . '/' . $name . '/' . $file);
                    }
                }
                if (is_dir($base . '/' . $name)) {
                    rmdir($base . '/' . $name);
                }
            }
            rmdir($base);
        }
    }
    /** @test */
    public function itRefusesToOverwriteExistingSnapshots(): void
    {
        $repo = dirname(__DIR__, 2);
        $destination = sys_get_temp_dir() . '/uganda-geo-existing-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($destination, 0700));
        $marker = $destination . '/marker.txt';
        file_put_contents($marker, 'retained output');
        try {
            $lines = [];
            $status = 0;
            exec(
                escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($repo . '/tools/release/build-dataset.php') . ' '
                . escapeshellarg($repo . '/src/Uganda/Data') . ' '
                . escapeshellarg($destination) . ' 2>&1',
                $lines,
                $status
            );
            self::assertSame(2, $status, implode("\n", $lines));
            self::assertSame('retained output', file_get_contents($marker));
        } finally {
            unlink($marker);
            rmdir($destination);
        }
    }
}
