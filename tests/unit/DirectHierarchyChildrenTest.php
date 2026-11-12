<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class DirectHierarchyChildrenTest extends TestCase
{
    /** @test */
    public function itPreservesImmediateChildIdentityAndOriginalOrder(): void
    {
        $first = new Village(24, 'Shared', 9);
        $second = new Village(18, 'Shared', 9);
        $parish = new Parish(9, 'Parish', 7, [24 => $first, 18 => $second]);
        $subcounty = new SubCounty(7, 'Subcounty', 5, [9 => $parish]);
        $county = new County(5, 'County', 3, [7 => $subcounty]);
        $district = new District(3, 'District', [5 => $county]);
        $index = new LocationIndex([3 => $district]);

        self::assertSame([$county], $index->childrenOf('county', 3));
        self::assertSame([$subcounty], $index->childrenOf('subcounty', 5));
        self::assertSame([$parish], $index->childrenOf('parish', 7));
        self::assertSame([$first, $second], $index->childrenOf('village', 9));
        self::assertSame([24, 18], array_map(static fn (Village $v): int => $v->id(), $index->childrenOf('village', 9)));
    }

    /** @test */
    public function aMatchingIdentifierAtTheWrongLevelNeverResolvesAChild(): void
    {
        $village = new Village(99, 'Village', 9);
        $parish = new Parish(9, 'Parish', 7, [99 => $village]);
        $subcounty = new SubCounty(7, 'Subcounty', 5, [9 => $parish]);
        $county = new County(5, 'County', 3, [7 => $subcounty]);
        $district = new District(3, 'District', [5 => $county]);
        $index = new LocationIndex([3 => $district]);

        self::assertSame([], $index->childrenOf('parish', 9));
        self::assertSame([], $index->childrenOf('county', 9));
        self::assertSame([$village], $index->childrenOf('village', 9));
    }

    /** @test */
    public function lookupsForMissingParentsDoNotModifyValidResults(): void
    {
        $county = new County(5, 'County', 3);
        $index = new LocationIndex([3 => new District(3, 'District', [5 => $county])]);

        self::assertSame([], $index->childrenOf('county', 999));
        self::assertSame([$county], $index->childrenOf('county', 3));
        self::assertSame([$county], $index->childrenOf('county', 3));
    }
}
