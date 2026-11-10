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
            $builder->verify($root, $manifest, ['b.json', 'a.json']);
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
    public function emptyManifestIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');
        (new ArtifactManifest())->build(sys_get_temp_dir(), []);
    }

    /** @test */
    public function refusesAnIncompleteReleaseInventory(): void
    {
        $root = sys_get_temp_dir() . '/uganda-geo-assets-' . bin2hex(random_bytes(6));
        mkdir($root);
        try {
            file_put_contents($root . '/dataset.json', '{}');
            $builder = new ArtifactManifest();
            $manifest = $builder->build($root, ['dataset.json']);
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('inventory mismatch');
            $builder->verify($root, $manifest, ['dataset.json', 'boundaries.geojson']);
        } finally {
            unlink($root . '/dataset.json');
            rmdir($root);
        }
    }

    /** @test */
    public function refusesAliasesToTheSamePhysicalReleaseArtifact(): void
    {
        $root = sys_get_temp_dir() . '/uganda-geo-alias-' . bin2hex(random_bytes(6));
        mkdir($root);
        try {
            file_put_contents($root . '/dataset.json', '{}');
            symlink('dataset.json', $root . '/alias.json');
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Duplicate artifact path');
            (new ArtifactManifest())->build($root, ['dataset.json', 'alias.json']);
        } finally {
            unlink($root . '/alias.json');
            unlink($root . '/dataset.json');
            rmdir($root);
        }
    }

    /** @test */
    public function refusesNormalizedPathAliasesBeforeHashing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArtifactManifest())->build(sys_get_temp_dir(), ['./any.json']);
    }

    /** @test */
    public function verifiesThatNoTwoManifestEntriesAliasTheSameFile(): void
    {
        $root = sys_get_temp_dir() . '/uganda-geo-verify-alias-' . bin2hex(random_bytes(6));
        mkdir($root);
        try {
            file_put_contents($root . '/dataset.json', '{}');
            symlink('dataset.json', $root . '/alias.json');
            $builder = new ArtifactManifest();
            $manifest = $builder->build($root, ['dataset.json']);
            $alias = $manifest['artifacts'][0];
            $alias['path'] = 'alias.json';
            $manifest['artifacts'][] = $alias;

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Duplicate physical release artifact');
            $builder->verify($root, $manifest, ['dataset.json', 'alias.json']);
        } finally {
            unlink($root . '/alias.json');
            unlink($root . '/dataset.json');
            rmdir($root);
        }
    }

    /** @test */
    public function rejectsAnEmptyManifestPresentedForVerification(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ArtifactManifest())->verify(sys_get_temp_dir(), [
            'schema_version' => 1, 'hash_algorithm' => 'sha256', 'artifacts' => [],
        ]);
    }
}
