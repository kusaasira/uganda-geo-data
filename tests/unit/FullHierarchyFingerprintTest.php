<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FullHierarchyFingerprintTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itProducesAStableSourceWideFingerprint(): void
    {
        $root = dirname(__DIR__, 2);
        $first = $this->fingerprint($root);
        $second = $this->fingerprint($root);

        self::assertSame($first['sha256'], $second['sha256']);
        self::assertSame($first['counts'], $second['counts']);
        self::assertSame(64, strlen($first['sha256']));
        self::assertSame(['district', 'county', 'subcounty', 'parish', 'village'], array_keys($first['counts']));

        foreach ([
            'district' => 'districts.json',
            'county' => 'counties.json',
            'subcounty' => 'sub_counties.json',
            'parish' => 'parishes.json',
            'village' => 'villages.json',
        ] as $level => $file) {
            $rows = json_decode(
                (string) file_get_contents($root . '/src/Uganda/Data/' . $file),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            self::assertCount($first['counts'][$level], $rows);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsAnUnusableRepositoryDirectory(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__, 2) . '/tools/benchmarks/full-hierarchy-fingerprint.php')
            . ' ' . escapeshellarg(sys_get_temp_dir() . '/uganda-geo-missing-source-root') . ' 2>&1';
        $lines = [];
        $exit = 0;
        exec($command, $lines, $exit);

        self::assertSame(2, $exit);
        self::assertStringContainsString('Usage:', implode("\n", $lines));
    }

    /** @return array<string, mixed> */
    private function fingerprint(string $root): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg($root . '/tools/benchmarks/full-hierarchy-fingerprint.php') . ' '
            . escapeshellarg($root) . ' 2>&1';
        $lines = [];
        $exit = 0;
        exec($command, $lines, $exit);
        self::assertSame(0, $exit, implode("\n", $lines));

        return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
    }
}
