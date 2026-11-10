<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class GeoCliSyntaxTest extends TestCase
{
    /** @test */
    public function allMaintainerCliScriptsHaveValidPhpSyntax(): void
    {
        if (!function_exists('exec')) {
            $this->markTestSkipped('CLI process execution is unavailable');
        }

        $root = dirname(__DIR__, 2);
        $scripts = ['tools/validate-source-registry.php', 'tools/geo/inspect-arcgis.php', 'tools/geo/download-arcgis.php'];
        foreach ($scripts as $script) {
            $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/' . $script) . ' 2>&1';
            $output = [];
            $status = 1;
            exec($command, $output, $status);
            $this->assertSame(0, $status, implode("\n", $output));
        }
    }
}
