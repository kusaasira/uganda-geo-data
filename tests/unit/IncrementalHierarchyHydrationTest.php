<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uganda\Uganda;

final class IncrementalHierarchyHydrationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/uganda-geo-hydration-' . bin2hex(random_bytes(6));
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function itResolvesZeroPaddedIdsAcrossEveryParentLink(): void
    {
        $this->writeValidRows();
        $geo = new Uganda($this->directory);
        $district = $geo->findById('district', 1);
        self::assertNotNull($district);
        $county = $geo->findById('county', 2);
        self::assertNotNull($county);
        $subcounty = $geo->findById('subcounty', 3);
        self::assertNotNull($subcounty);
        $parish = $geo->findById('parish', 4);
        self::assertNotNull($parish);
        $village = $geo->findById('village', 5);
        self::assertNotNull($village);

        self::assertSame([$county], $geo->childrenOf('county', 1));
        self::assertSame([$subcounty], $geo->childrenOf('subcounty', 2));
        self::assertSame([$parish], $geo->childrenOf('parish', 3));
        self::assertSame([$village], $geo->childrenOf('village', 4));
        self::assertSame([$district, $county, $subcounty, $parish], $geo->ancestorsOf('village', 5));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicateNamesWithinOneParentAreNotDeduplicated(): void
    {
        $this->writeValidRows();
        $this->write('villages', [
            ['id' => '5', 'name' => 'Central', 'parish' => '4'],
            ['id' => '6', 'name' => 'Central', 'parish' => '0004'],
        ]);
        $geo = new Uganda($this->directory);

        $matching = $geo->searchByName('village', 'Central', 4);
        self::assertSame([5, 6], array_map(static fn ($village): int => $village->id(), $matching));
        self::assertCount(2, $geo->childrenOf('village', 4));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function aliasIdsThatCastToTheSameIntegerAreNotSilentlyOverwritten(): void
    {
        $this->writeValidRows();
        $this->write('villages', [
            ['id' => '005', 'name' => 'First', 'parish' => '4'],
            ['id' => '5', 'name' => 'Second', 'parish' => '4'],
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Duplicate village ID: 5');
        new Uganda($this->directory);
    }

    private function writeValidRows(): void
    {
        $this->write('districts', [['id' => '001', 'name' => 'District']]);
        $this->write('counties', [['id' => '002', 'name' => 'County', 'district' => '1']]);
        $this->write('sub_counties', [['id' => '003', 'name' => 'Subcounty', 'county' => '0002']]);
        $this->write('parishes', [['id' => '004', 'name' => 'Parish', 'subcounty' => '3']]);
        $this->write('villages', [['id' => '005', 'name' => 'Village', 'parish' => '0004']]);
    }

    /** @param array<int, array<string, string>> $rows */
    private function write(string $level, array $rows): void
    {
        file_put_contents($this->directory . '/' . $level . '.json', json_encode($rows, JSON_THROW_ON_ERROR));
    }
}
