<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class ReleaseReadmeContractsTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function publishedReadmeUsesExistingEntrypointsAndScopedExampleInputs(): void
    {
        $root = dirname(__DIR__, 2);
        $readme = file_get_contents($root . '/README.md');

        self::assertIsString($readme);
        self::assertStringContainsString('composer require "kusaasira/uganda-geo:^2.0"', $readme);
        self::assertStringContainsString('Uganda Bureau of Statistics', $readme);
        self::assertStringContainsString("searchByName('parish', 'Central', \$subcounty->id())", $readme);
        self::assertStringNotContainsString('$subcountyId', $readme);
        self::assertStringContainsString('mkdir -p build', $readme);

        foreach ([
            'tools/geo/inspect-arcgis.php',
            'tools/geo/download-arcgis.php',
            'tools/geo/export-formats.php',
            'examples/standalone-v2.php',
            'docs/migration/v1-to-v2.md',
        ] as $path) {
            self::assertFileExists($root . '/' . $path);
        }

        self::assertSame(0, substr_count($readme, '```') % 2, 'README code fences must be balanced');
    }
}
