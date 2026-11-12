<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationPresenter;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class LocationJsonSerializationTest extends TestCase
{
    /** @test */
    public function itSerializesAllFiveLevelsWithExplicitIdentityAndParentContext(): void
    {
        $models = [
            new District(1, 'District'),
            new County(2, 'County', 1),
            new SubCounty(3, 'Subcounty', 2),
            new Parish(4, 'Parish', 3),
            new Village(5, 'Village', 4),
        ];

        $decoded = json_decode(json_encode($models, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            ['id' => 1, 'name' => 'District', 'level' => 'district', 'parent_id' => null],
            ['id' => 2, 'name' => 'County', 'level' => 'county', 'parent_id' => 1],
            ['id' => 3, 'name' => 'Subcounty', 'level' => 'subcounty', 'parent_id' => 2],
            ['id' => 4, 'name' => 'Parish', 'level' => 'parish', 'parent_id' => 3],
            ['id' => 5, 'name' => 'Village', 'level' => 'village', 'parent_id' => 4],
        ], $decoded);
    }

    /** @test */
    public function itDoesNotAccidentallySerializeEveryDescendant(): void
    {
        $village = new Village(5, 'Village', 4);
        $parish = new Parish(4, 'Parish', 3, [5 => $village]);
        $county = new County(2, 'County', 1, [3 => new SubCounty(3, 'Subcounty', 2, [4 => $parish])]);
        $district = new District(1, 'District', [2 => $county]);

        $decoded = json_decode(json_encode($district, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['id', 'name', 'level', 'parent_id'], array_keys($decoded));
        self::assertSame('district', $decoded['level']);

        // Explicit toArray() retains the existing nested PHP API contract.
        self::assertArrayHasKey('counties', $district->toArray());
        self::assertArrayHasKey(2, $district->toArray()['counties']);
    }

    /** @test */
    public function distinctSameNamePlacesRemainDistinctWhenSerialized(): void
    {
        $locations = [new Parish(10, 'Central', 3), new Parish(11, 'Central', 3)];
        $decoded = json_decode(json_encode($locations, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([10, 11], array_column($decoded, 'id'));
    }

    /** @test */
    public function itRejectsUnsupportedModelsRatherThanFabricatingLocationFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LocationPresenter::toArray(new \stdClass());
    }
}
