<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class PackageMetadataTest extends TestCase
{
    /** @test */
    public function composerMetadataMatchesTheDistributionContract(): void
    {
        $path = dirname(__DIR__, 2) . '/composer.json';
        $raw = file_get_contents($path);
        $this->assertNotFalse($raw);

        $composer = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('kusaasira/uganda-geo', $composer['name']);
        $this->assertSame('MIT', $composer['license']);
        $this->assertSame('^8.2', $composer['require']['php']);
        $this->assertSame('src/Uganda/', $composer['autoload']['psr-4']['Uganda\\\\']);
    }

    /** @test */
    public function frameworkDiscoveryNeverReferencesAnUninstalledProvider(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        $this->assertNotFalse($raw);
        $composer = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('laravel', $composer['extra'] ?? []);
        $this->assertArrayNotHasKey('files', $composer['autoload']);
    }
}
