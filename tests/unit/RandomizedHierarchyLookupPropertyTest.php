<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\HierarchyOracle;
use Tests\Support\SeededHierarchyFixture;
use Uganda\Lookup\LocationIndex;

require_once dirname(__DIR__) . '/support/SeededHierarchyFixture.php';
require_once dirname(__DIR__) . '/support/HierarchyOracle.php';

final class RandomizedHierarchyLookupPropertyTest extends TestCase
{
    #[Test]
    public function all_seeded_exact_name_and_id_searches_match_an_independent_oracle(): void
    {
        for ($seed = 1; $seed <= 96; $seed++) {
            $districts = (new SeededHierarchyFixture($seed * 1093))->generate();
            $levels = HierarchyOracle::flatten($districts);
            $index = new LocationIndex($districts);

            foreach ($levels as $level => $models) {
                self::assertNotEmpty($models, "seed=$seed level=$level");
                $offsets = array_unique([0, intdiv(count($models), 2), count($models) - 1]);

                foreach ($offsets as $offset) {
                    $unit = $models[$offset];
                    self::assertSame($unit, $index->byId($level, $unit->id()), "seed=$seed level=$level ID");

                    foreach ([$unit->name(), '  ' . strtoupper($unit->name()) . '  '] as $query) {
                        self::assertSame(
                            HierarchyOracle::ids(HierarchyOracle::exact($models, $query)),
                            HierarchyOracle::ids($index->byName($level, $query)),
                            "seed=$seed level=$level name=$query"
                        );

                        $parent = HierarchyOracle::parentId($unit);
                        if ($parent !== null) {
                            self::assertSame(
                                HierarchyOracle::ids(HierarchyOracle::exact($models, $query, $parent)),
                                HierarchyOracle::ids($index->byName($level, $query, $parent)),
                                "seed=$seed level=$level scoped=$query parent=$parent"
                            );
                        }
                    }
                }

                self::assertNull($index->byId($level, 999999999), "seed=$seed level=$level missing ID");
                self::assertSame([], $index->byName($level, 'not-a-real-place', 999999999));
            }
        }
    }

    #[Test]
    public function scoped_first_search_and_global_index_first_search_are_equivalent(): void
    {
        foreach ([3, 7, 11, 37, 71, 99, 151, 233] as $seed) {
            $districts = (new SeededHierarchyFixture($seed))->generate();
            $levels = HierarchyOracle::flatten($districts);

            foreach (['county', 'subcounty', 'parish', 'village'] as $level) {
                $models = $levels[$level];
                $unit = $models[intdiv(count($models), 2)];
                $parent = HierarchyOracle::parentId($unit);
                self::assertNotNull($parent);

                $scopedFirst = new LocationIndex($districts);
                $globalFirst = new LocationIndex($districts);
                $globalFirst->byName($level, $unit->name());

                $expected = HierarchyOracle::ids(HierarchyOracle::exact($models, $unit->name(), $parent));
                self::assertSame($expected, HierarchyOracle::ids(
                    $scopedFirst->byName($level, $unit->name(), $parent)
                ), "seed=$seed level=$level scoped first");
                self::assertSame($expected, HierarchyOracle::ids(
                    $globalFirst->byName($level, $unit->name(), $parent)
                ), "seed=$seed level=$level global first");
            }
        }
    }
}
