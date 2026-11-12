<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Data\SourceRegistryValidator;

final class SourceRegistryTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
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
    #[\PHPUnit\Framework\Attributes\Test]
    public function rejectsInvalidCalendarDatesAndDuplicateSourceIdentifiers(): void
    {
        $registry = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/resources/sources.json'), true, 512, JSON_THROW_ON_ERROR);
        $registry['sources'][0]['reference_date'] = '2026-02-31';
        $registry['sources'][1]['id'] = $registry['sources'][0]['id'];
        $errors = (new SourceRegistryValidator())->errors($registry);
        $this->assertStringContainsString('invalid reference_date', implode(' ', $errors));
        $this->assertStringContainsString('Duplicate source identifier', implode(' ', $errors));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function doesNotAcceptRedistributionWithoutLicenseAndAttribution(): void
    {
        $registry = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/resources/sources.json'), true, 512, JSON_THROW_ON_ERROR);
        $registry['sources'][0]['redistribution_status'] = 'permitted';
        $errors = (new SourceRegistryValidator())->errors($registry);
        $this->assertStringContainsString('requires license URL and attribution', implode(' ', $errors));
    }
}
