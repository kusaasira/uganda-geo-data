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

final class LocationIndexLazyChildrenTest extends TestCase
{
    private function index(): LocationIndex
    {
        $firstVillage = new Village(91, 'Central', 51);
        $secondVillage = new Village(92, 'Central', 51);
        $parish = new Parish(51, 'North', 31, [91 => $firstVillage, 92 => $secondVillage]);
        $subcounty = new SubCounty(31, 'East', 21, [51 => $parish]);
        $county = new County(21, 'Lower Madi', 11, [31 => $subcounty]);

        return new LocationIndex([11 => new District(11, 'Northern', [21 => $county])]);
    }

    /** @test */
    public function itBuildsIndependentChildLevelIndexesWithoutChangingResults(): void
    {
        $index = $this->index();

        self::assertSame([21], array_map(static fn ($unit): int => $unit->id(), $index->childrenOf('county', 11)));
        self::assertSame([91, 92], array_map(static fn ($unit): int => $unit->id(), $index->childrenOf('village', 51)));
        self::assertSame([31], array_map(static fn ($unit): int => $unit->id(), $index->childrenOf('subcounty', 21)));
        self::assertSame([51], array_map(static fn ($unit): int => $unit->id(), $index->childrenOf('parish', 31)));
    }

    /** @test */
    public function itReusesChildIndexesForRepeatedQueries(): void
    {
        $index = $this->index();

        self::assertSame($index->childrenOf('village', 51), $index->childrenOf('village', 51));
        self::assertSame($index->childrenOf('county', 11), $index->childrenOf('county', 11));
    }

    /** @test */
    public function itPreservesNameMatchesWhenOtherChildLevelsHaveBeenIndexed(): void
    {
        $index = $this->index();
        $index->childrenOf('county', 11);
        $index->childrenOf('village', 51);

        self::assertCount(2, $index->byName('village', 'Central', 51));
        self::assertSame(91, $index->byName('village', 'Central', 51)[0]->id());
        self::assertSame(92, $index->byName('village', 'Central', 51)[1]->id());
    }

    /** @test */
    public function anUnknownParentDoesNotChangeSubsequentValidLookups(): void
    {
        $index = $this->index();

        self::assertSame([], $index->childrenOf('village', 999999));
        self::assertCount(2, $index->childrenOf('village', 51));
        self::assertSame([], $index->childrenOf('parish', 999999));
        self::assertCount(1, $index->childrenOf('parish', 31));
    }
}
