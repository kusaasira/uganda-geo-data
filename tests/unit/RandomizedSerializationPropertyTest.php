<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\HierarchyOracle;
use Tests\Support\SeededHierarchyFixture;
use Uganda\District;

require_once dirname(__DIR__) . '/support/SeededHierarchyFixture.php';
require_once dirname(__DIR__) . '/support/HierarchyOracle.php';

final class RandomizedSerializationPropertyTest extends TestCase
{
    #[Test]
    public function generated_records_json_encode_to_their_exact_source_id_name_and_parent(): void
    {
        for ($seed = 1; $seed <= 72; $seed++) {
            $districts = (new SeededHierarchyFixture($seed * 797))->generate();
            $levels = HierarchyOracle::flatten($districts);
            foreach ($levels as $level => $records) {
                $samples = array_unique([0, intdiv(count($records), 2), count($records) - 1]);

                foreach ($samples as $offset) {
                    $unit = $records[$offset];
                    $flat = json_decode(json_encode($unit, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                    self::assertSame(
                        ['id' => $unit->id(), 'name' => $unit->name(),
                            'level' => $level, 'parent_id' => HierarchyOracle::parentId($unit)],
                        $flat,
                        "seed=$seed level=$level index=$offset"
                    );
                    self::assertSame(
                        ['id', 'name', 'level', 'parent_id'],
                        array_keys($flat),
                        "seed=$seed level=$level JSON must not recursively hydrate descendants"
                    );
                }
            }
        }
    }

    #[Test]
    public function nested_to_array_retains_all_descendants_after_flat_json_serialization(): void
    {
        for ($seed = 1; $seed <= 48; $seed++) {
            $districts = (new SeededHierarchyFixture($seed * 1103))->generate();
            $counties = HierarchyOracle::flatten($districts)['county'];
            foreach ($districts as $district) {
                self::assertInstanceOf(District::class, $district);
                $json = json_decode(json_encode($district, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                self::assertArrayNotHasKey('counties', $json);
                $tree = $district->toArray();
                self::assertArrayHasKey('counties', $tree);
                self::assertCount(count($district->counties()), $tree['counties']);
            }
            self::assertNotEmpty($counties, "seed=$seed");
        }
    }
}
