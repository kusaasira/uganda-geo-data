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

final class ScopedNameFastPathTest extends TestCase
{
    /** @return array{index: LocationIndex, siblings: array<int, Village>} */
    private function fixture(): array
    {
        $siblings = [
            11 => new Village(11, 'Central', 9),
            12 => new Village(12, 'Central', 9),
            13 => new Village(13, 'Other', 9),
        ];
        $parish = new Parish(9, 'Parish', 7, $siblings);
        $subcounty = new SubCounty(7, 'Subcounty', 5, [9 => $parish]);
        $county = new County(5, 'County', 3, [7 => $subcounty]);

        return ['index' => new LocationIndex([3 => new District(3, 'District', [5 => $county])]), 'siblings' => $siblings];
    }

    #[Test]
    public function parent_scoped_name_search_avoids_a_global_name_index(): void
    {
        ['index' => $index, 'siblings' => $siblings] = $this->fixture();

        self::assertSame([$siblings[11], $siblings[12]], $index->byName('village', '  CENTRAL  ', 9));
        self::assertSame([], (new ReflectionProperty(LocationIndex::class, 'byName'))->getValue($index));

        $byId = (new ReflectionProperty(LocationIndex::class, 'byId'))->getValue($index);
        self::assertArrayHasKey('parish', $byId);
        self::assertArrayNotHasKey('village', $byId);
    }

    #[Test]
    public function scoped_results_remain_identical_after_unscoped_index_warmup(): void
    {
        ['index' => $index, 'siblings' => $siblings] = $this->fixture();
        $cold = $index->byName('village', 'central', 9);
        $index->byName('village', 'central');
        $warm = $index->byName('village', 'central', 9);

        self::assertSame([$siblings[11], $siblings[12]], $cold);
        self::assertSame($cold, $warm);
        self::assertSame([], $index->byName('village', 'central', 999999));
    }

    #[Test]
    public function a_district_cannot_match_an_unsupported_parent_scope(): void
    {
        ['index' => $index] = $this->fixture();
        self::assertSame([], $index->byName('district', 'District', 3));
    }

    #[Test]
    public function unknown_levels_still_throw_with_a_parent_scope(): void
    {
        ['index' => $index] = $this->fixture();
        $this->expectException(\InvalidArgumentException::class);
        $index->byName('province', 'Example', 9);
    }
}
