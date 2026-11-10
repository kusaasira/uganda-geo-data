<?php

declare(strict_types=1);

namespace unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class HierarchyNavigationTest extends TestCase
{
    private function index(): LocationIndex
    {
        $village = new Village(5, 'Central', 4);
        $parish = new Parish(4, 'Central', 3, [5 => $village]);
        $subcounty = new SubCounty(3, 'East', 2, [4 => $parish]);
        $county = new County(2, 'Lower Madi', 1, [3 => $subcounty]);

        return new LocationIndex([1 => new District(1, 'North', [2 => $county])]);
    }

    /** @test */
    public function itRetrievesChildrenThroughExactParentIdentity(): void
    {
        $index = $this->index();
        $children = $index->childrenOf('village', 4);
        $this->assertCount(1, $children);
        $this->assertSame(5, $children[0]->id());
        $this->assertSame([], $index->childrenOf('village', 99));
        $this->assertSame(2, $index->childrenOf('county', 1)[0]->id());
    }

    /** @test */
    public function itReturnsAllAncestorsInRootFirstOrder(): void
    {
        $index = $this->index();
        $ids = array_map(static function ($unit): int {
            return $unit->id();
        }, $index->ancestorsOf('village', 5));
        $this->assertSame([1, 2, 3, 4], $ids);
        $this->assertSame([], $index->ancestorsOf('district', 1));
        $this->assertSame([], $index->ancestorsOf('village', 999));
    }

    /** @test */
    public function repeatedChildNamesArePreservedAndTheLazyIndexIsReusable(): void
    {
        $first = new County(2, 'Central', 1, []);
        $second = new County(9, 'Central', 1, []);
        $index = new LocationIndex([1 => new District(1, 'Parent', [2 => $first, 9 => $second])]);

        $this->assertSame([2, 9], array_map(static function ($unit): int {
            return $unit->id();
        }, $index->childrenOf('county', 1)));
        $this->assertSame($first, $index->childrenOf('county', 1)[0]);
        $this->assertSame($second, $index->childrenOf('county', 1)[1]);
        $this->assertCount(2, $index->byName('county', 'Central', 1));
    }

    /** @test */
    public function rootCannotBeQueriedAsAChildOfAnUnknownParent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->index()->childrenOf('district', 1);
    }
}
