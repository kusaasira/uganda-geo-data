<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class ExplorerCoverageBuildCommandTest extends TestCase
{
    /** @test */
    public function optionalReviewedCoverageIsBoundToTheCanonicalSnapshot(): void
    {
        $repo = dirname(__DIR__, 2);
        $destination = sys_get_temp_dir() . '/uganda-coverage-explorer-' . bin2hex(random_bytes(6));
        try {
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($repo . '/tools/data/build-explorer.php') . ' '
                . escapeshellarg($repo . '/tests/fixtures/canonical-snapshot.json') . ' '
                . escapeshellarg($destination) . ' '
                . escapeshellarg($repo . '/tests/fixtures/reviewed-coverage.json') . ' 2>&1';
            $lines = [];
            $exit = 0;
            exec($command, $lines, $exit);
            self::assertSame(0, $exit, implode("\n", $lines));
            $index = json_decode(
                (string) file_get_contents($destination . '/data.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            self::assertSame(1, $index['geometry_coverage']['reviewed']);
            self::assertSame(1, $index['geometry_coverage']['total']);
            self::assertSame('unverified', $index['geometry_coverage']['national_coverage']);
            self::assertArrayNotHasKey('verified_matches', $index['geometry_coverage']);
        } finally {
            foreach (['data.json', 'index.html', 'assets/app.js', 'assets/style.css'] as $file) {
                if (is_file($destination . '/' . $file)) {
                    unlink($destination . '/' . $file);
                }
            }
            if (is_dir($destination . '/assets')) {
                rmdir($destination . '/assets');
            }
            if (is_dir($destination)) {
                rmdir($destination);
            }
        }
    }
}
