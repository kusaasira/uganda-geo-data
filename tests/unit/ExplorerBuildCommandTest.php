<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class ExplorerBuildCommandTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itBuildsACompleteStaticExplorerAndRefusesOverwrites(): void
    {
        $repo = dirname(__DIR__, 2);
        $destination = sys_get_temp_dir() . '/uganda-explorer-' . bin2hex(random_bytes(8));
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repo . '/tools/data/build-explorer.php')
            . ' ' . escapeshellarg($repo . '/tests/fixtures/canonical-snapshot.json')
            . ' ' . escapeshellarg($destination) . ' 2>&1';
        try {
            exec($command, $output, $code);
            $this->assertSame(0, $code, implode("\n", $output));
            foreach (['data.json', 'index.html', 'assets/app.js', 'assets/style.css'] as $file) {
                $this->assertFileExists($destination . '/' . $file);
            }
            $index = json_decode((string) file_get_contents($destination . '/data.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('contract-test-snapshot', $index['snapshot_id']);
            $this->assertSame(5, $index['unit_count']);

            exec($command, $again, $secondCode);
            $this->assertSame(2, $secondCode);
            $this->assertSame('contract-test-snapshot', $index['snapshot_id']);
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
