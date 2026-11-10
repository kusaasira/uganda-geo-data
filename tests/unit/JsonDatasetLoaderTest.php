<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Uganda\Data\JsonDatasetLoader;
use Uganda\Uganda;

final class JsonDatasetLoaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/uganda-geo-load-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    /** @test */
    public function rejectsMissingFileInsteadOfReturningEmptyHierarchy(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or unreadable dataset');
        (new JsonDatasetLoader())->load($this->directory . '/missing.json');
    }

    /** @test */
    public function rejectsMalformedJsonWithFileContext(): void
    {
        file_put_contents($this->directory . '/broken.json', '{broken');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in');
        (new JsonDatasetLoader())->load($this->directory . '/broken.json');
    }

    /** @test */
    public function rejectsEmptyAndObjectShapedTables(): void
    {
        file_put_contents($this->directory . '/rows.json', '[]');
        try {
            (new JsonDatasetLoader())->load($this->directory . '/rows.json');
            $this->fail('Empty required datasets must fail');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nonempty list', $exception->getMessage());
        }
        file_put_contents($this->directory . '/rows.json', '{"row":{"id":"1","name":"A"}}');
        $this->expectException(RuntimeException::class);
        (new JsonDatasetLoader())->load($this->directory . '/rows.json');
    }

    /** @test */
    public function rejectsInvalidRecordsAndPreservesValidRows(): void
    {
        $path = $this->directory . '/rows.json';
        file_put_contents($path, '[{"id":"7","name":"One"},{"id":"8","name":"One"}]');
        $rows = (new JsonDatasetLoader())->load($path);
        $this->assertCount(2, $rows);
        $this->assertSame('8', $rows[1]['id']);

        file_put_contents($path, '[{"id":null,"name":"One"}]');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('record at index 0');
        (new JsonDatasetLoader())->load($path);
    }

    /** @test */
    public function rejectsCorruptFilesDuringActualUgandaConstruction(): void
    {
        foreach (['districts', 'counties', 'sub_counties', 'parishes', 'villages'] as $tier) {
            file_put_contents($this->directory . '/' . $tier . '.json', '[{"id":"1","name":"Example"}]');
        }
        file_put_contents($this->directory . '/villages.json', '{invalid');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON in');
        new Uganda($this->directory);
    }
}
