<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class PackageMetadataTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function composerMetadataMatchesTheDistributionContract(): void
    {
        $path = dirname(__DIR__, 2) . '/composer.json';
        $raw = file_get_contents($path);
        $this->assertNotFalse($raw);

        $composer = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('kusaasira/uganda-geo', $composer['name']);
        $this->assertSame('MIT', $composer['license']);
        $this->assertSame('^8.2', $composer['require']['php']);
        $this->assertSame('src/Uganda/', $composer['autoload']['psr-4']['Uganda' . chr(92)]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function frameworkDiscoveryReferencesAnExistingOptionalProvider(): void
    {
        $raw = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        $this->assertNotFalse($raw);
        $composer = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        $provider = $composer['extra']['laravel']['providers'][0] ?? null;
        $this->assertSame('Uganda' . chr(92) . 'Laravel' . chr(92) . 'UgandaGeoServiceProvider', $provider);
        $this->assertFileExists(dirname(__DIR__, 2) . '/integrations/laravel/UgandaGeoServiceProvider.php');
        $this->assertArrayNotHasKey('files', $composer['autoload']);
    }
}
