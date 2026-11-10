<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Import\LegacyJsonImporter;

final class LegacyJsonImporterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/uganda-geo-import-' . uniqid('', true);
        mkdir($this->dir);
        $this->write('districts.json', [['id' => '1', 'name' => 'Sample District']]);
        $this->write('counties.json', [['id' => '2', 'name' => 'Sample County', 'district' => '1']]);
        $this->write('sub_counties.json', [['id' => '3', 'name' => 'Sample Subcounty', 'county' => '2']]);
        $this->write('parishes.json', [['id' => '4', 'name' => 'Same', 'subcounty' => '3'], ['id' => '5', 'name' => 'Same', 'subcounty' => '3']]);
        $this->write('villages.json', [['id' => '6', 'name' => 'Same', 'parish' => '4'], ['id' => '7', 'name' => 'Same', 'parish' => '4']]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    /** @param array<int, array<string, string>> $rows */
    private function write(string $file, array $rows): void
    {
        file_put_contents($this->dir . '/' . $file, json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /** @test */
    public function preservesSameNameRowsAndParentIds(): void
    {
        $snapshot = (new LegacyJsonImporter())->import($this->dir);
        $this->assertCount(7, $snapshot['units']);
        $this->assertSame('legacy:parish:4', $snapshot['units'][5]['parent_id']);
        $this->assertSame('legacy:parish:4', $snapshot['units'][6]['parent_id']);
        $this->assertSame('legacy:parish:5', $snapshot['units'][4]['id']);
        $this->assertSame(
            json_encode($snapshot, JSON_THROW_ON_ERROR),
            json_encode((new LegacyJsonImporter())->import($this->dir), JSON_THROW_ON_ERROR)
        );
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('"external_codes":{}', $json);
        $this->assertSame([], json_decode($json, true, 512, JSON_THROW_ON_ERROR)['units'][0]['external_codes']);
    }

    /** @test */
    public function rejectsDuplicateIdsInsteadOfOverwriting(): void
    {
        $this->write('parishes.json', [['id' => '4', 'name' => 'A', 'subcounty' => '3'], ['id' => '4', 'name' => 'B', 'subcounty' => '3']]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate source identifier');
        (new LegacyJsonImporter())->import($this->dir);
    }

    /** @test */
    public function rejectsMissingParentReferences(): void
    {
        $this->write('counties.json', [['id' => '2', 'name' => 'County']]);
        $this->expectException(RuntimeException::class);
        (new LegacyJsonImporter())->import($this->dir);
    }

    /** @test */
    public function rejectsMalformedJson(): void
    {
        file_put_contents($this->dir . '/villages.json', '{broken');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');
        (new LegacyJsonImporter())->import($this->dir);
    }
    /** @test */
    public function rejectsObjectShapedSourceTables(): void
    {
        file_put_contents($this->dir . '/districts.json', '{"unit":{"id":"1","name":"Sample"}}');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected a JSON list');
        (new LegacyJsonImporter())->import($this->dir);
    }

    /** @test */
    public function rejectsEmptyRequiredHierarchyTables(): void
    {
        $this->write('villages.json', []);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('source file is empty');
        (new LegacyJsonImporter())->import($this->dir);
    }
}
