<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class AncestryMemoizationTest extends TestCase
{
    private function index(): LocationIndex
    {
        $village = new Village(5, 'Village', 4);
        $parish = new Parish(4, 'Parish', 3, [5 => $village]);
        $subcounty = new SubCounty(3, 'Subcounty', 2, [4 => $parish]);
        $county = new County(2, 'County', 1, [3 => $subcounty]);
        return new LocationIndex([1 => new District(1, 'District', [2 => $county])]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itCachesSuccessfulAncestryWithoutChangingObjectIdentity(): void
    {
        $index = $this->index();
        $first = $index->ancestorsOf('village', 5);
        $second = $index->ancestorsOf('village', 5);

        self::assertSame($first, $second);
        self::assertSame([1, 2, 3, 4], array_map(static fn ($x): int => $x->id(), $second));

        $property = new ReflectionProperty(LocationIndex::class, 'ancestryCache');
        $cache = $property->getValue($index);
        self::assertArrayHasKey(5, $cache['village']);
        self::assertSame($first, $cache['village'][5]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itDoesNotCacheArbitraryUnknownIds(): void
    {
        $index = $this->index();
        self::assertSame([], $index->ancestorsOf('village', 999999));

        $property = new ReflectionProperty(LocationIndex::class, 'ancestryCache');
        $cache = $property->getValue($index);
        self::assertArrayNotHasKey('village', $cache);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function modifyingAResultArrayCannotMutateTheCachedPath(): void
    {
        $index = $this->index();
        $first = $index->ancestorsOf('village', 5);
        $first[0] = new District(111, 'Unrelated');

        $repeat = $index->ancestorsOf('village', 5);
        self::assertSame(1, $repeat[0]->id());
        self::assertCount(4, $repeat);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function aDistrictWithoutParentsReturnsAnEmptyValidPath(): void
    {
        $index = $this->index();
        self::assertSame([], $index->ancestorsOf('district', 1));
        self::assertSame([], $index->ancestorsOf('district', 1));
    }
}
