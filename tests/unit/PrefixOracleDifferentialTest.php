<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;

final class PrefixOracleDifferentialTest extends TestCase
{
    #[Test]
    public function indexed_and_scoped_prefix_paths_match_a_linear_stable_oracle(): void
    {
        /** @var array<int, array<int, County>> $groups */
        $groups = [];
        $names = ['Central', 'Centenary', 'Cen', 'North', 'Nakawa', '12', '123', 'One'];
        for ($id = 1; $id <= 1000; $id++) {
            $parentId = 1 + ($id % 10);
            $name = $names[$id % count($names)];
            if ($id % 3 === 0) {
                $name .= ' ' . sprintf('%02d', $id % 23);
            }
            $groups[$parentId][$id] = new County($id, $name, $parentId);
        }

        $districts = [];
        for ($parentId = 1; $parentId <= 10; $parentId++) {
            $districts[$parentId] = new District($parentId, 'District ' . $parentId, $groups[$parentId] ?? []);
        }

        $sourceOrder = [];
        foreach ($districts as $district) {
            foreach ($district->counties() as $county) {
                $sourceOrder[] = $county;
            }
        }
        $ordinals = [];
        foreach ($sourceOrder as $ordinal => $county) {
            $ordinals[$county->id()] = $ordinal;
        }

        $scopedFirst = new LocationIndex($districts);
        $globalFirst = new LocationIndex($districts);
        $globalFirst->byPrefix('county', 'cen');

        foreach (['ce', 'cen', 'na', 'no', '12', '1', 'one', 'missing'] as $prefix) {
            foreach ([null, 1, 5, 10, 999] as $parentId) {
                foreach ([null, 1, 3, 25] as $limit) {
                    $expected = array_values(array_filter(
                        $sourceOrder,
                        static fn (County $county): bool => ($parentId === null
                            || $county->districtId() === $parentId)
                            && str_starts_with(strtolower(trim($county->name())), $prefix)
                    ));
                    usort($expected, static function (County $a, County $b) use ($ordinals): int {
                        $cmp = strcmp(strtolower(trim($a->name())), strtolower(trim($b->name())));
                        return $cmp !== 0 ? $cmp : $ordinals[$a->id()] <=> $ordinals[$b->id()];
                    });
                    if ($limit !== null) {
                        $expected = array_slice($expected, 0, $limit);
                    }
                    $expectedIds = array_map(static fn (County $c): int => $c->id(), $expected);
                    $label = "$prefix parent=" . ($parentId === null ? 'all' : $parentId)
                        . ' limit=' . ($limit === null ? 'all' : $limit);

                    if ($parentId !== null) {
                        self::assertSame($expectedIds, array_map(
                            static fn (County $c): int => $c->id(),
                            $scopedFirst->byPrefix('county', $prefix, $parentId, $limit)
                        ), 'Scoped-first ' . $label);
                    }

                    self::assertSame($expectedIds, array_map(
                        static fn (County $c): int => $c->id(),
                        $globalFirst->byPrefix('county', $prefix, $parentId, $limit)
                    ), 'Global-first ' . $label);
                }
            }
        }
    }
}
