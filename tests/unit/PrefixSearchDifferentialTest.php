<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;

final class PrefixSearchDifferentialTest extends TestCase
{
    /** @test */
    public function indexedPrefixMatchesAGroundTruthScanAcrossHundredsOfUnits(): void
    {
        $counties = [];
        for ($id = 1; $id <= 500; $id++) {
            $counties[$id] = new County($id, sprintf('Area %04d', $id), 901);
        }
        $index = new LocationIndex([901 => new District(901, 'Parent', $counties)]);

        foreach (['area 00', 'area 01', 'area 032', 'area 05', 'no match'] as $prefix) {
            $expected = array_values(array_filter($counties, static function (County $county) use ($prefix): bool {
                return str_starts_with(strtolower($county->name()), $prefix);
            }));
            $actual = $index->byPrefix('county', $prefix);
            self::assertSame(
                array_map(static fn (County $county): int => $county->id(), $expected),
                array_map(static fn (County $county): int => $county->id(), $actual)
            );
        }
    }

    /** @test */
    public function numericOnlyNamesAreTreatedAsTextNotArrayIndexes(): void
    {
        $counties = [
            1 => new County(1, '12', 901),
            2 => new County(2, '123', 901),
            3 => new County(3, '1234', 901),
        ];
        $index = new LocationIndex([901 => new District(901, 'Parent', $counties)]);

        self::assertSame(
            [1, 2, 3],
            array_map(static fn ($county): int => $county->id(), $index->byPrefix('county', '1'))
        );
        self::assertSame(
            [2],
            array_map(static fn ($county): int => $county->id(), $index->byName('county', '123'))
        );
    }
}
