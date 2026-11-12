<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class ScopedPrefixFastPathTest extends TestCase
{
    private function index(): LocationIndex
    {
        $villages = [
            19 => new Village(19, 'Central', 10),
            17 => new Village(17, 'Centenary', 10),
            22 => new Village(22, 'Cen', 10),
            28 => new Village(28, 'Central', 10),
            30 => new Village(30, 'Other', 10),
            32 => new Village(32, 'Central', 12),
        ];
        $parishes = [
            10 => new Parish(10, 'Parish A', 8, array_slice($villages, 0, 5, true)),
            12 => new Parish(12, 'Parish B', 8, [32 => $villages[32]]),
        ];
        $subcounty = new SubCounty(8, 'Subcounty', 7, $parishes);
        $county = new County(7, 'County', 6, [8 => $subcounty]);
        return new LocationIndex([6 => new District(6, 'District', [7 => $county])]);
    }

    #[Test]
    public function scoped_prefix_matches_global_index_order_without_global_index_build(): void
    {
        $index = $this->index();
        $expected = [22, 17, 19, 28];

        self::assertSame($expected, array_map(
            static fn (Village $v): int => $v->id(),
            $index->byPrefix('village', ' cen ', 10)
        ));
        self::assertSame([], (new ReflectionProperty(LocationIndex::class, 'byName'))->getValue($index));
        self::assertSame([], (new ReflectionProperty(LocationIndex::class, 'sortedNameKeys'))->getValue($index));

        // Global search warms the index. A second scoped search must retain
        // exactly the same order, duplicates and limit semantics.
        self::assertCount(5, $index->byPrefix('village', 'cen'));
        self::assertSame($expected, array_map(
            static fn (Village $v): int => $v->id(),
            $index->byPrefix('village', 'cen', 10)
        ));
        self::assertSame([22, 17], array_map(
            static fn (Village $v): int => $v->id(),
            $index->byPrefix('village', 'cen', 10, 2)
        ));
    }

    #[Test]
    public function empty_results_and_unknown_parents_are_deterministic(): void
    {
        $index = $this->index();
        self::assertSame([], $index->byPrefix('village', 'zzz', 10));
        self::assertSame([], $index->byPrefix('village', 'cen', 999999));
    }

    #[Test]
    public function prefixed_root_locations_with_an_explicit_parent_remain_empty(): void
    {
        $index = $this->index();
        self::assertSame([], $index->byPrefix('district', 'dis', 6));
    }

    #[Test]
    public function invalid_limits_and_levels_remain_rejected(): void
    {
        $index = $this->index();
        $this->expectException(\InvalidArgumentException::class);
        $index->byPrefix('village', 'cen', 10, 0);
    }
}
