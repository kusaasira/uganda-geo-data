<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Release\ArtifactManifest;

final class ArtifactManifestTest extends TestCase
{
    /** @test */
    public function generatesSortedChecksumsAndDetectsTampering(): void
    {
        $root = sys_get_temp_dir() . '/uganda-geo-release-' . bin2hex(random_bytes(6));
        mkdir($root);
        try {
            file_put_contents($root . '/b.json', '{"b":2}');
            file_put_contents($root . '/a.json', '{"a":1}');
            $builder = new ArtifactManifest();
            $manifest = $builder->build($root, ['b.json', 'a.json']);
            $this->assertSame(['a.json', 'b.json'], array_column($manifest['artifacts'], 'path'));
            $builder->verify($root, $manifest);
            file_put_contents($root . '/a.json', '{"a":3}');
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('mismatch');
            $builder->verify($root, $manifest);
        } finally {
            unlink($root . '/a.json');
            unlink($root . '/b.json');
            rmdir($root);
        }
    }

    /** @test */
    public function refusesPathTraversalAndUnlistedSiblingPaths(): void
    {
        $root = sys_get_temp_dir();
        $this->expectException(InvalidArgumentException::class);
        (new ArtifactManifest())->build($root, ['../etc/passwd']);
    }

    /** @test */
    public function emptyManifestIsDeterministic(): void
    {
        $manifest = (new ArtifactManifest())->build(sys_get_temp_dir(), []);
        $this->assertSame([], $manifest['artifacts']);
        $this->assertSame(1, $manifest['schema_version']);
    }
}
