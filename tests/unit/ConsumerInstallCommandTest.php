<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ConsumerInstallCommandTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsUnknownModesBeforeRunningComposer(): void
    {
        $output = [];
        $status = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(dirname(__DIR__, 2) . '/tools/ci/consumer-install.php')
            . ' --unknown 2>&1',
            $output,
            $status
        );
        self::assertSame(2, $status);
        self::assertStringContainsString('Usage:', implode("\n", $output));
    }
}
