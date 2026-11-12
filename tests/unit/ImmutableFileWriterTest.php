<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Geo\ImmutableFileWriter;

final class ImmutableFileWriterTest extends TestCase
{
    #[Test]
    public function writes_binary_payloads_completely_without_changing_their_bytes(): void
    {
        $folder = $this->temporaryDirectory();
        try {
            $payload = "Uganda\x00Geo\n" . str_repeat("Districts\n", 1024);
            $path = $folder . '/manifest.json';
            ImmutableFileWriter::write($path, $payload);

            self::assertSame($payload, file_get_contents($path));
            self::assertSame(strlen($payload), filesize($path));
            self::assertSame(hash('sha256', $payload), hash_file('sha256', $path));
            self::assertSame([], glob($folder . '/.uganda-geo-stage-*') ?: []);
        } finally {
            $this->removeFixtureDirectory($folder);
        }
    }

    #[Test]
    public function refuses_to_overwrite_an_existing_release_file(): void
    {
        $folder = $this->temporaryDirectory();
        try {
            $path = $folder . '/output.geojson';
            file_put_contents($path, 'original evidence bytes');
            try {
                ImmutableFileWriter::write($path, 'unexpected replacement');
                self::fail('An existing acquisition artifact was overwritten');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Cannot create', $exception->getMessage());
            }
            self::assertSame('original evidence bytes', file_get_contents($path));
        } finally {
            $this->removeFixtureDirectory($folder);
        }
    }

    #[Test]
    public function refuses_dangling_symlinks_instead_of_following_them(): void
    {
        $folder = $this->temporaryDirectory();
        try {
            $path = $folder . '/dangling-link';
            if (!function_exists('symlink') || !@symlink($folder . '/missing-target', $path)) {
                self::markTestSkipped('Creating symbolic links is unavailable on this filesystem');
            }
            try {
                ImmutableFileWriter::write($path, 'must not be written');
                self::fail('Writer followed an existing symlink');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Cannot create', $exception->getMessage());
            }
            self::assertTrue(is_link($path));
            self::assertFileDoesNotExist($folder . '/missing-target');
            self::assertSame([], glob($folder . '/.uganda-geo-stage-*') ?: []);
        } finally {
            $this->removeFixtureDirectory($folder);
        }
    }

    #[Test]
    public function an_unavailable_parent_does_not_create_partial_files(): void
    {
        $folder = $this->temporaryDirectory();
        try {
            $path = $folder . '/missing/metadata.json';
            $this->expectException(RuntimeException::class);
            ImmutableFileWriter::write($path, 'metadata');
        } finally {
            $this->removeFixtureDirectory($folder);
        }
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . '/geo-immutable-writer-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($path, 0700));

        return $path;
    }

    private function removeFixtureDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
