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
        $snapshot = ['schema_version' => 1, 'snapshot_id' => 'test-v1', 'units' => [
            ['id' => 'parish:2', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:2', 'confidential' => 'omit'],
            ['id' => 'parish:1', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:1'],
            ['id' => 'subcounty:1', 'name' => 'North', 'level' => 'subcounty', 'parent_id' => null],
            ['id' => 'subcounty:2', 'name' => 'South', 'level' => 'subcounty', 'parent_id' => null],
        ]];
        $index = (new ExplorerIndexBuilder())->build($snapshot);
        $this->assertSame(4, $index['unit_count']);
        $this->assertSame(['parish:1', 'parish:2', 'subcounty:1', 'subcounty:2'], array_column($index['units'], 'id'));
        $this->assertArrayNotHasKey('confidential', $index['units'][1]);
        $this->assertSame('test-v1', $index['snapshot_id']);
    }

    /** @test */
    public function rejectsDuplicateIdsInsteadOfOverwriting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'snapshot_id' => 'test', 'units' => [
            ['id' => 'village:1', 'name' => 'First', 'level' => 'village'],
            ['id' => 'village:1', 'name' => 'Second', 'level' => 'village'],
        ]]);
    }
    /** @test */
    public function itRejectsAnOrphanRatherThanDisplayingAFalseCompleteHierarchy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing canonical parent');
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'snapshot_id' => 'test', 'units' => [
            ['id' => 'parish:1', 'name' => 'Central', 'level' => 'parish', 'parent_id' => 'subcounty:404'],
        ]]);
    }

    /** @test */
    public function itRejectsCyclicAdministrativeAncestry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cyclic canonical ancestry');
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'snapshot_id' => 'test', 'units' => [
            ['id' => 'a', 'name' => 'A', 'level' => 'parish', 'parent_id' => 'b'],
            ['id' => 'b', 'name' => 'B', 'level' => 'subcounty', 'parent_id' => 'a'],
        ]]);
    }

    /** @test */
    public function itRejectsAnUnidentifiedSnapshot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ExplorerIndexBuilder())->build(['schema_version' => 1, 'units' => []]);
    }

}
