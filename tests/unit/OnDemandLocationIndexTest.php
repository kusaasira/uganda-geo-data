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

final class OnDemandLocationIndexTest extends TestCase
{
    private function index(): LocationIndex
    {
        $village = new Village(5, 'Central', 4);
        $parish = new Parish(4, 'Central', 3, [5 => $village]);
        $subcounty = new SubCounty(3, 'East', 2, [4 => $parish]);
        $county = new County(2, 'Lower Madi', 1, [3 => $subcounty]);

        return new LocationIndex([1 => new District(1, 'North', [2 => $county])]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function constructingTheIndexAllocatesNoSecondaryIdentityOrNameTables(): void
    {
        $index = $this->index();
        self::assertSame([], $this->state($index, 'byId'));
        self::assertSame([], $this->state($index, 'byName'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function directChildLookupIndexesOnlyItsParentAdministrativeLevel(): void
    {
        $index = $this->index();
        self::assertSame([5], array_map(static fn (Village $unit): int => $unit->id(), $index->childrenOf('village', 4)));

        self::assertSame(['parish'], array_keys($this->state($index, 'byId')));
        self::assertSame([], $this->state($index, 'byName'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function idFirstLookupDoesNotBuildAnyNameIndex(): void
    {
        $index = $this->index();
        self::assertSame(5, $index->byId('village', 5)->id());

        self::assertSame(['village'], array_keys($this->state($index, 'byId')));
        self::assertSame([], $this->state($index, 'byName'));

        self::assertSame([5], array_map(static fn (Village $unit): int => $unit->id(), $index->byName('village', 'Central')));
        self::assertSame(['village'], array_keys($this->state($index, 'byName')));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function firstNameLookupBuildsOnlyTheRequestedLevel(): void
    {
        $index = $this->index();
        self::assertSame([2], array_map(static fn (County $unit): int => $unit->id(), $index->byName('county', 'Lower Madi')));

        self::assertSame(['county'], array_keys($this->state($index, 'byId')));
        self::assertSame(['county'], array_keys($this->state($index, 'byName')));
        self::assertSame([], $this->state($index, 'sortedNameKeys'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function onlyPrefixSearchNeedsTheSortedNameTable(): void
    {
        $index = $this->index();
        $index->byName('village', 'central');
        self::assertSame([], $this->state($index, 'sortedNameKeys'));

        self::assertCount(1, $index->byPrefix('village', 'cen'));
        self::assertSame(['village'], array_keys($this->state($index, 'sortedNameKeys')));
    }

    private function state(LocationIndex $index, string $property): array
    {
        $reflection = new ReflectionProperty(LocationIndex::class, $property);
        $value = $reflection->getValue($index);
        self::assertIsArray($value);

        return $value;
    }
}
