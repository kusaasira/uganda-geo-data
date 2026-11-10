<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;

final class CanonicalSnapshotContractTest extends TestCase
{
    /** @test */
    public function schemaAndFixtureAgreeOnIdentityAndParentLinks(): void
    {
        $root = dirname(__DIR__, 2);
        $schema = json_decode((string) file_get_contents($root . '/resources/schemas/administrative-unit.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $snapshotSchema = json_decode((string) file_get_contents($root . '/resources/schemas/canonical-snapshot.schema.json'), true, 512, JSON_THROW_ON_ERROR);
        $fixture = json_decode((string) file_get_contents($root . '/tests/fixtures/canonical-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $fixture['schema_version']);
        $this->assertSame(
            'https://raw.githubusercontent.com/kusaasira/uganda-geo-data/next/2.x/resources/schemas/administrative-unit.schema.json',
            $schema['$id']
        );
        $this->assertSame('administrative-unit.schema.json', $snapshotSchema['properties']['units']['items']['$ref']);
        $this->assertSame('object', $schema['properties']['external_codes']['type']);
        $this->assertSame(1, $snapshotSchema['properties']['schema_version']['const']);
        $this->assertContains('parish', $schema['properties']['level']['enum']);
        $this->assertContains('ward', $schema['properties']['level']['enum']);
        $this->assertContains('cell', $schema['properties']['level']['enum']);

        $units = [];
        foreach ($fixture['units'] as $unit) {
            foreach ($schema['required'] as $field) {
                $this->assertArrayHasKey($field, $unit);
            }
            $this->assertArrayNotHasKey($unit['id'], $units);
            $units[$unit['id']] = $unit;
        }
        foreach ($units as $unit) {
            if ($unit['parent_id'] !== null) {
                $this->assertArrayHasKey($unit['parent_id'], $units);
                $this->assertNotSame($unit['id'], $unit['parent_id']);
            }
        }
    }
}
