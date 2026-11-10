<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\Data\ExplorerIndexBuilder;

final class ExplorerIndexBuilderTest extends TestCase
{
    /** @test */
    public function retainsDuplicateNamesByDistinctCanonicalIdentity(): void
    {
        $snapshot = ['snapshot_id' => 'test-v1', 'units' => [
            ['id' => 'parish:2', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:2', 'confidential' => 'omit'],
            ['id' => 'parish:1', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:1'],
        ]];
        $index = (new ExplorerIndexBuilder())->build($snapshot);
        $this->assertSame(2, $index['unit_count']);
        $this->assertSame(['parish:1', 'parish:2'], array_column($index['units'], 'id'));
        $this->assertArrayNotHasKey('confidential', $index['units'][1]);
        $this->assertSame('test-v1', $index['snapshot_id']);
    }

    /** @test */
    public function rejectsDuplicateIdsInsteadOfOverwriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExplorerIndexBuilder())->build(['units' => [
            ['id' => 'village:1', 'name' => 'First', 'level' => 'village'],
            ['id' => 'village:1', 'name' => 'Second', 'level' => 'village'],
        ]]);
    }
}
