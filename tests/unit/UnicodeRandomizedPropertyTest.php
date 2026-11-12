<?php

declare(strict_types=1);

namespace Tests\Unit;

use Normalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\HierarchyOracle;
use Tests\Support\SeededHierarchyFixture;
use Uganda\Lookup\LocationIndex;
use Uganda\Lookup\UnicodeEditDistance;
use Uganda\Lookup\UnicodeNameNormalizer;

require_once dirname(__DIR__) . '/support/HierarchyOracle.php';
require_once dirname(__DIR__) . '/support/SeededHierarchyFixture.php';

final class UnicodeRandomizedPropertyTest extends TestCase
{
    #[Test]
    public function seeded_scoped_and_global_fuzzy_queries_match_a_linear_reference_ranking(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('ICU required for Unicode property tests');
        }

        for ($seed = 1; $seed <= 35; $seed++) {
            $districts = (new SeededHierarchyFixture($seed * 1471))->generate();
            $levels = HierarchyOracle::flatten($districts);
            $index = new LocationIndex($districts);

            foreach ($levels as $level => $source) {
                $sample = $source[intdiv(count($source), 2)];
                $parents = [null, HierarchyOracle::parentId($sample)];
                $queryForms = [
                    $sample->name(),
                    strtoupper($sample->name()) . 'a',
                    'notareallocation',
                ];

                foreach ($queryForms as $query) {
                    foreach ($parents as $parentId) {
                        foreach ([false, true] as $fold) {
                            $normalized = UnicodeNameNormalizer::normalize($query, $fold);
                            foreach ([0, 1, 2] as $distanceLimit) {
                                $expected = [];
                                foreach ($source as $ordinal => $unit) {
                                    if ($parentId !== null && HierarchyOracle::parentId($unit) !== $parentId) {
                                        continue;
                                    }
                                    $key = UnicodeNameNormalizer::normalize($unit->name(), $fold);
                                    $distance = UnicodeEditDistance::within($normalized, $key, $distanceLimit);
                                    if ($distance !== null) {
                                        $expected[] = [
                                            'unit' => $unit, 'distance' => $distance,
                                            'key' => $key, 'ordinal' => $ordinal,
                                        ];
                                    }
                                }
                                usort($expected, static fn (array $a, array $b): int =>
                                    ($a['distance'] <=> $b['distance'])
                                    ?: strcmp($a['key'], $b['key'])
                                    ?: ($a['ordinal'] <=> $b['ordinal']));
                                $expected = array_slice($expected, 0, 3);

                                $actual = $index->fuzzy($level, $query, $parentId, 3, $distanceLimit, $fold);
                                self::assertSame(
                                    array_map(static fn (array $e): array => [$e['unit']->id(), $e['distance']], $expected),
                                    array_map(static fn (array $a): array => [$a['unit']->id(), $a['distance']], $actual),
                                    "seed=$seed level=$level query=$query parent=" .
                                        ($parentId ?? 'global') . " limit=$distanceLimit fold=" . (int) $fold
                                );
                            }
                        }
                    }
                }
            }
        }
    }
}
