<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class ReleaseManifestCommandTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itEnforcesTheIndependentInventoryAndRefusesToOverwriteExistingManifests(): void
    {
        $directory = sys_get_temp_dir() . '/uganda-geo-release-cmd-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $manifestPath = $directory . '/manifest.json';
        $assetsDirectory = $directory . '/assets';
        mkdir($assetsDirectory);
        try {
            file_put_contents($assetsDirectory . '/data.json', '{"count":1}');
            $prefix = escapeshellarg(PHP_BINARY) . ' ';
            $create = $prefix . escapeshellarg(dirname(__DIR__, 2) . '/tools/release/create-manifest.php')
                . ' ' . escapeshellarg($assetsDirectory) . ' ' . escapeshellarg($manifestPath)
                . ' data.json 2>&1';
            $verify = $prefix . escapeshellarg(dirname(__DIR__, 2) . '/tools/release/verify-manifest.php')
                . ' ' . escapeshellarg($assetsDirectory) . ' ' . escapeshellarg($manifestPath)
                . ' data.json 2>&1';

            exec($create, $createOutput, $createCode);
            $this->assertSame(0, $createCode, implode("\n", $createOutput));
            $originalManifest = (string) file_get_contents($manifestPath);

            exec($verify, $verifyOutput, $verifyCode);
            $this->assertSame(0, $verifyCode, implode("\n", $verifyOutput));

            exec($create, $overwriteOutput, $overwriteCode);
            $this->assertSame(2, $overwriteCode);
            $this->assertSame($originalManifest, file_get_contents($manifestPath));

            exec($verify . ' missing.geojson', $missingOutput, $missingCode);
            $this->assertSame(1, $missingCode);
        } finally {
            if (is_file($manifestPath)) {
                unlink($manifestPath);
            }
            if (is_file($assetsDirectory . '/data.json')) {
                unlink($assetsDirectory . '/data.json');
            }
            rmdir($assetsDirectory);
            rmdir($directory);
        }
    }
}
