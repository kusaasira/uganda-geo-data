<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class ReleaseNotesCommandSafetyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itPreservesExistingReleaseNotesOnRejectedWrites(): void
    {
        $root = dirname(__DIR__, 2);
        $path = tempnam(sys_get_temp_dir(), 'uganda-geo-notes-');
        self::assertIsString($path);
        file_put_contents($path, 'previous release notes');
        try {
            $lines = [];
            $status = 0;
            exec(
                escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($root . '/tools/release/build-notes.php') . ' '
                . escapeshellarg($root . '/resources/releases/v2-notes.json') . ' '
                . 'v2.0.0-rc.1 ' . escapeshellarg($path) . ' 2>&1',
                $lines,
                $status
            );
            self::assertSame(1, $status);
            self::assertSame('previous release notes', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
