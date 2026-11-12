<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\HierarchyOracle;
use Tests\Support\SeededHierarchyFixture;
use Uganda\Lookup\LocationIndex;

require_once dirname(__DIR__) . '/support/SeededHierarchyFixture.php';
require_once dirname(__DIR__) . '/support/HierarchyOracle.php';

final class MixedCacheWorkloadPropertyTest extends TestCase
{
    #[Test]
    public function arbitrary_query_order_never_changes_results_or_exceeds_cache_caps(): void
    {
        $levels = ['district', 'county', 'subcounty', 'parish', 'village'];

        for ($seed = 1; $seed <= 40; $seed++) {
            $random = new SeededHierarchyFixture($seed * 179);
            $districts = $random->generate();
            $flattened = HierarchyOracle::flatten($districts);
            $index = new LocationIndex($districts);

            for ($step = 0; $step < 400; $step++) {
                $level = $levels[$random->nextInt(0, count($levels) - 1)];
                $source = $flattened[$level];
                $unit = $source[$random->nextInt(0, count($source) - 1)];
                $parent = HierarchyOracle::parentId($unit);
                $operation = $random->nextInt(0, 6);
                $tag = "seed=$seed step=$step level=$level operation=$operation";

                switch ($operation) {
                    case 0:
                        self::assertSame($unit, $index->byId($level, $unit->id()), $tag);
                        break;
                    case 1:
                        self::assertSame(
                            HierarchyOracle::ids(HierarchyOracle::exact($source, $unit->name(), $parent)),
                            HierarchyOracle::ids($index->byName($level, $unit->name(), $parent)),
                            $tag
                        );
                        break;
                    case 2:
                        $prefix = substr(strtolower($unit->name()), 0, 2);
                        $limit = $random->nextInt(1, 4);
                        self::assertSame(
                            HierarchyOracle::ids(HierarchyOracle::prefix($source, $prefix, $parent, $limit)),
                            HierarchyOracle::ids($index->byPrefix($level, $prefix, $parent, $limit)),
                            $tag
                        );
                        break;
                    case 3:
                        self::assertSame(
                            HierarchyOracle::ids(HierarchyOracle::exact($source, $unit->name())),
                            HierarchyOracle::ids($index->byName($level, $unit->name())),
                            $tag
                        );
                        break;
                    case 4:
                        self::assertNull($index->byId($level, 90000000 + $step), $tag);
                        break;
                    case 5:
                        self::assertSame([], $index->byName($level, 'does-not-exist', 90000000 + $step), $tag);
                        break;
                    case 6:
                        self::assertSame(
                            HierarchyOracle::ids(HierarchyOracle::prefix($source, 'cen')),
                            HierarchyOracle::ids($index->byPrefix($level, 'cen')),
                            $tag
                        );
                        break;
                }
            }

            $ancestry = (new ReflectionProperty(LocationIndex::class, 'ancestryCacheSlots'))->getValue($index);
            $scoped = (new ReflectionProperty(LocationIndex::class, 'scopedNameCacheSlots'))->getValue($index);
            self::assertLessThanOrEqual(512, count($ancestry), "seed=$seed ancestry cap");
            self::assertLessThanOrEqual(256, count($scoped), "seed=$seed scoped name cap");
        }
    }
}
