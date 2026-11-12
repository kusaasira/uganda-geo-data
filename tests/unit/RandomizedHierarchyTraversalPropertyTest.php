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

final class RandomizedHierarchyTraversalPropertyTest extends TestCase
{
    #[Test]
    public function direct_children_and_ancestry_remain_correct_for_every_generated_unit(): void
    {
        $parents = [
            'county' => 'district',
            'subcounty' => 'county',
            'parish' => 'subcounty',
            'village' => 'parish',
        ];

        for ($seed = 1; $seed <= 96; $seed++) {
            $districts = (new SeededHierarchyFixture($seed * 3571))->generate();
            $byLevel = HierarchyOracle::flatten($districts);
            $index = new LocationIndex($districts);
            $byId = [];
            foreach ($byLevel as $level => $records) {
                foreach ($records as $unit) {
                    $byId[$level][$unit->id()] = $unit;
                }

                self::assertSame(
                    HierarchyOracle::ids($records),
                    HierarchyOracle::ids(iterator_to_array($index->iterate($level), false)),
                    "seed=$seed level=$level streaming"
                );
            }

            foreach ($parents as $childLevel => $parentLevel) {
                foreach ($byLevel[$parentLevel] as $parent) {
                    $expectedChildren = array_values(array_filter(
                        $byLevel[$childLevel],
                        static fn (object $unit): bool => HierarchyOracle::parentId($unit) === $parent->id()
                    ));
                    self::assertSame(
                        HierarchyOracle::ids($expectedChildren),
                        HierarchyOracle::ids($index->childrenOf($childLevel, $parent->id())),
                        "seed=$seed level=$childLevel parent=" . $parent->id()
                    );
                }
            }

            foreach ($byLevel as $level => $records) {
                foreach ($records as $unit) {
                    $chain = [];
                    $currentLevel = $level;
                    $current = $unit;
                    while (isset($parents[$currentLevel])) {
                        $parentLevel = $parents[$currentLevel];
                        $parentId = HierarchyOracle::parentId($current);
                        self::assertNotNull($parentId);
                        $current = $byId[$parentLevel][$parentId];
                        $chain[] = $current;
                        $currentLevel = $parentLevel;
                    }
                    $expected = array_reverse($chain);
                    self::assertSame(
                        $expected,
                        $index->ancestorsOf($level, $unit->id()),
                        "seed=$seed level=$level unit=" . $unit->id()
                    );
                }
            }
        }
    }
}
