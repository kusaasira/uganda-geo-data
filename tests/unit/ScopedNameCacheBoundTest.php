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

final class ScopedNameCacheBoundTest extends TestCase
{
    #[Test]
    public function cache_evicts_older_scopes_without_changing_lookup_results(): void
    {
        $parishes = [];
        for ($id = 1; $id <= 400; $id++) {
            $villageId = 1000 + $id;
            $parishes[$id] = new Parish($id, 'Parish ' . $id, 9, [
                $villageId => new Village($villageId, 'Central', $id),
            ]);
        }
        $subcounty = new SubCounty(9, 'Subcounty', 8, $parishes);
        $county = new County(8, 'County', 7, [9 => $subcounty]);
        $index = new LocationIndex([7 => new District(7, 'District', [8 => $county])]);

        for ($id = 1; $id <= 400; $id++) {
            $matches = $index->byName('village', 'Central', $id);
            self::assertCount(1, $matches);
            self::assertSame(1000 + $id, $matches[0]->id());
        }

        $cache = (new ReflectionProperty(LocationIndex::class, 'scopedNameCache'))->getValue($index);
        $slots = (new ReflectionProperty(LocationIndex::class, 'scopedNameCacheSlots'))->getValue($index);
        self::assertCount(256, $slots);
        self::assertCount(256, $cache['village']);
        self::assertArrayNotHasKey(1, $cache['village']);
        self::assertArrayHasKey(400, $cache['village']);

        self::assertSame(1001, $index->byName('village', 'Central', 1)[0]->id());
        $cache = (new ReflectionProperty(LocationIndex::class, 'scopedNameCache'))->getValue($index);
        self::assertCount(256, $cache['village']);

        // A global name index replaces rather than duplicates the bounded
        // scoped indexes for the same administrative level.
        self::assertCount(400, $index->byName('village', 'Central'));
        $cache = (new ReflectionProperty(LocationIndex::class, 'scopedNameCache'))->getValue($index);
        $slots = (new ReflectionProperty(LocationIndex::class, 'scopedNameCacheSlots'))->getValue($index);
        self::assertArrayNotHasKey('village', $cache);
        self::assertSame([], $slots);
    }

    #[Test]
    public function large_same_parent_collisions_are_not_cached(): void
    {
        $villages = [];
        for ($id = 1; $id <= 35; $id++) {
            $villages[$id] = new Village($id, 'Central', 10);
        }
        $parish = new Parish(10, 'Parish', 9, $villages);
        $subcounty = new SubCounty(9, 'Subcounty', 8, [10 => $parish]);
        $county = new County(8, 'County', 7, [9 => $subcounty]);
        $index = new LocationIndex([7 => new District(7, 'District', [8 => $county])]);

        self::assertCount(35, $index->byName('village', 'Central', 10));
        self::assertCount(35, $index->byName('village', 'Central', 10));

        $cache = (new ReflectionProperty(LocationIndex::class, 'scopedNameCache'))->getValue($index);
        self::assertSame([], $cache);
    }
}
