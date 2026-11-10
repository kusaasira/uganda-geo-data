<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Exceptions\AmbiguousLocationException;
use Uganda\Lookup\LocationIndex;

final class LocationIndexTest extends TestCase
{
    /** @return array<int, District> */
    private function sample(): array
    {
        return [
            1 => new District(1, 'First', [8 => new County(8, 'Lower Madi', 1)]),
            2 => new District(2, 'Second', [309 => new County(309, 'Lower Madi', 2)]),
        ];
    }

    /** @test */
    public function preservesDistinctSameNameLocationsAndRespectsParentIds(): void
    {
        $index = new LocationIndex($this->sample());
        $this->assertCount(2, $index->byName('county', ' lower madi '));
        $this->assertSame(8, $index->byName('county', 'lower madi', 1)[0]->id());
        $this->assertSame(309, $index->byName('county', 'lower madi', 2)[0]->id());
        $this->assertSame(309, $index->byId('county', 309)->id());
        $this->assertNull($index->byId('county', 50000));
    }

    /** @test */
    public function requiresExplicitDisambiguationForSameNameSiblingCounties(): void
    {
        $district = new District(1, 'First', [
            8 => new County(8, 'Same', 1),
            9 => new County(9, 'Same', 1),
        ]);
        $this->expectException(AmbiguousLocationException::class);
        $district->county('same');
    }

    /** @test */
    public function rejectsUnknownHierarchyLevels(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new LocationIndex($this->sample()))->byName('province', 'unknown');
    }
}
