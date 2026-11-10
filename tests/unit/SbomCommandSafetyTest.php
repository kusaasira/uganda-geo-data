<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class SbomCommandSafetyTest extends TestCase
{
    /** @test */
    public function existingOutputIsNeverDeletedOrOverwritten(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'uganda-geo-sbom-');
        self::assertIsString($path);
        file_put_contents($path, 'original SBOM bytes');
        try {
            $output = [];
            $status = 0;
            exec(
                escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg(dirname(__DIR__, 2) . '/tools/release/create-sbom.php')
                . ' 2.0.0-dev ' . escapeshellarg($path) . ' 2>&1',
                $output,
                $status
            );
            self::assertSame(1, $status);
            self::assertSame('original SBOM bytes', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
