<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class NestedCollectionIdentityTest extends TestCase
{
    #[Test]
    public function all_flattened_model_collections_preserve_order_duplicates_and_object_identity(): void
    {
        $first = new Village(90, 'Central', 11);
        $second = new Village(20, 'Central', 11);
        $third = new Village(99, 'Central', 19);
        $fourth = new Village(40, 'Other', 21);
        $p1 = new Parish(11, 'Shared', 7, [90 => $first, 20 => $second]);
        $p2 = new Parish(19, 'Shared', 7, [99 => $third]);
        $p3 = new Parish(21, 'Another', 8, [40 => $fourth]);
        $s1 = new SubCounty(7, 'One', 5, [11 => $p1, 19 => $p2]);
        $s2 = new SubCounty(8, 'Two', 6, [21 => $p3]);
        $c1 = new County(5, 'County A', 1, [7 => $s1]);
        $c2 = new County(6, 'County B', 1, [8 => $s2]);
        $district = new District(1, 'District', [5 => $c1, 6 => $c2]);

        self::assertSame([$s1, $s2], $district->subCounties());
        self::assertSame([$p1, $p2, $p3], $district->parishes());
        self::assertSame([$first, $second, $third, $fourth], $district->villages());

        self::assertSame([$p1, $p2], $c1->parishes());
        self::assertSame([$first, $second, $third], $c1->villages());
        self::assertSame([$first, $second, $third], $s1->villages());

        foreach ([$district->subCounties(), $district->parishes(), $district->villages(),
            $c1->parishes(), $c1->villages(), $s1->villages()] as $collection) {
            self::assertTrue(array_is_list($collection));
        }

        // Duplicate display names remain distinct identities.
        self::assertSame([90, 20, 99, 40], array_map(
            static fn (Village $village): int => $village->id(),
            $district->villages()
        ));
    }
}
