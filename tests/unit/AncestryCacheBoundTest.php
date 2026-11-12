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

final class AncestryCacheBoundTest extends TestCase
{
    #[Test]
    public function repeated_distinct_queries_cannot_retain_unbounded_ancestry_paths(): void
    {
        $villages = [];
        for ($id = 1; $id <= 900; $id++) {
            $villages[$id] = new Village($id, 'Village ' . $id, 80);
        }

        $parish = new Parish(80, 'Parish', 70, $villages);
        $subcounty = new SubCounty(70, 'Subcounty', 60, [80 => $parish]);
        $county = new County(60, 'County', 50, [70 => $subcounty]);
        $district = new District(50, 'District', [60 => $county]);
        $index = new LocationIndex([50 => $district]);

        for ($id = 1; $id <= 900; $id++) {
            self::assertSame([$district, $county, $subcounty, $parish], $index->ancestorsOf('village', $id));
        }

        $cache = (new ReflectionProperty(LocationIndex::class, 'ancestryCache'))->getValue($index);
        $slots = (new ReflectionProperty(LocationIndex::class, 'ancestryCacheSlots'))->getValue($index);

        self::assertCount(512, $cache['village']);
        self::assertCount(512, $slots);
        self::assertArrayNotHasKey(1, $cache['village']);
        self::assertArrayHasKey(900, $cache['village']);

        // Eviction must not affect correctness for the earliest location.
        self::assertSame([$district, $county, $subcounty, $parish], $index->ancestorsOf('village', 1));
        $cache = (new ReflectionProperty(LocationIndex::class, 'ancestryCache'))->getValue($index);
        self::assertCount(512, $cache['village']);
        self::assertArrayHasKey(1, $cache['village']);
    }

    #[Test]
    public function missing_identifiers_never_occupy_a_cache_slot(): void
    {
        $index = new LocationIndex([1 => new District(1, 'District')]);

        for ($id = 1; $id <= 1000; $id++) {
            self::assertSame([], $index->ancestorsOf('village', $id));
        }

        $slots = (new ReflectionProperty(LocationIndex::class, 'ancestryCacheSlots'))->getValue($index);
        self::assertSame([], $slots);
    }
}
