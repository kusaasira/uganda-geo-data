<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class UbosReferenceProvenanceTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function usesOfficialUbosPublicationHostsForBothReferenceDocuments(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/data/ubos-2024-reference.json';
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $manifest['publications']);
        foreach ($manifest['publications'] as $publication) {
            $this->assertSame('https', parse_url($publication['url'], PHP_URL_SCHEME));
            $host = parse_url($publication['url'], PHP_URL_HOST);
            $this->assertContains($host, ['www.ubos.org', 'statistics.ubos.org']);
        }
    }
}
