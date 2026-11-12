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

final class RandomizedPrefixPropertyTest extends TestCase
{
    #[Test]
    public function randomized_scoped_and_global_prefixes_preserve_stable_identity_order(): void
    {
        for ($seed = 1; $seed <= 64; $seed++) {
            $districts = (new SeededHierarchyFixture($seed * 7919))->generate();
            $levels = HierarchyOracle::flatten($districts);
            $globalFirst = new LocationIndex($districts);
            $scopedFirst = new LocationIndex($districts);

            foreach ($levels as $level => $models) {
                $sample = $models[intdiv(count($models), 2)];
                $parent = HierarchyOracle::parentId($sample);
                $prefixes = array_unique([
                    substr(strtolower($sample->name()), 0, 2),
                    substr(strtolower($sample->name()), 0, 4),
                    'cen', '12', 'é', 'missing',
                ]);

                foreach ($prefixes as $prefix) {
                    foreach ([null, $parent, 99999999] as $scope) {
                        foreach ([null, 1, 3] as $limit) {
                            $expected = HierarchyOracle::ids(HierarchyOracle::prefix($models, $prefix, $scope, $limit));
                            $description = "seed=$seed level=$level prefix=$prefix parent="
                                . ($scope === null ? 'none' : $scope) . ' limit='
                                . ($limit === null ? 'all' : $limit);

                            self::assertSame(
                                $expected,
                                HierarchyOracle::ids($scopedFirst->byPrefix($level, $prefix, $scope, $limit)),
                                'scoped-first ' . $description
                            );
                            self::assertSame(
                                $expected,
                                HierarchyOracle::ids($globalFirst->byPrefix($level, $prefix, $scope, $limit)),
                                'global-first ' . $description
                            );
                        }
                    }
                }
            }
        }
    }

    #[Test]
    public function a_nonpositive_prefix_limit_is_always_rejected(): void
    {
        $districts = (new SeededHierarchyFixture(20261112))->generate();
        $index = new LocationIndex($districts);
        $this->expectException(\InvalidArgumentException::class);
        $index->byPrefix('village', 'c', null, -10);
    }
}
