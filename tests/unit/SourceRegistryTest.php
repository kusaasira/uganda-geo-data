<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class SourceRegistryTest extends TestCase
{
    /** @test */
    public function sourceRegistryRetainsDistinctProvenanceAndRights(): void
    {
        $data = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/resources/sources.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $data['schema_version']);
        $this->assertNotEmpty($data['sources']);
        $ids = [];
        foreach ($data['sources'] as $source) {
            $this->assertNotEmpty($source['url']);
            $this->assertSame('https', parse_url($source['url'], PHP_URL_SCHEME));
            $this->assertNotEmpty($source['publisher']);
            $this->assertArrayHasKey('redistribution_status', $source);
            $this->assertArrayHasKey('reference_date', $source);
            $this->assertNotContains($source['id'], $ids);
            $ids[] = $source['id'];
        }
    }
}
