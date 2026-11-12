<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class OnDemandIndexIdentityTest extends TestCase
{
    /** @test */
    public function allQueryOrdersReturnTheExactSameSourceObjects(): void
    {
        $first = new Village(33, 'Central', 12);
        $second = new Village(34, 'Central', 12);
        $parish = new Parish(12, 'Parish', 11, [33 => $first, 34 => $second]);
        $subcounty = new SubCounty(11, 'Subcounty', 10, [12 => $parish]);
        $county = new County(10, 'County', 9, [11 => $subcounty]);
        $root = [9 => new District(9, 'District', [10 => $county])];

        foreach (['by_id', 'name', 'prefix', 'children', 'ancestors'] as $firstOperation) {
            $index = new LocationIndex($root);
            switch ($firstOperation) {
                case 'by_id':
                    $index->byId('village', 33);
                    break;
                case 'name':
                    $index->byName('village', 'Central', 12);
                    break;
                case 'prefix':
                    $index->byPrefix('village', 'Cen', 12);
                    break;
                case 'children':
                    $index->childrenOf('village', 12);
                    break;
                case 'ancestors':
                    $index->ancestorsOf('village', 33);
                    break;
            }

            self::assertSame($first, $index->byId('village', 33));
            self::assertSame([$first, $second], $index->byName('village', 'CENTRAL', 12));
            self::assertSame([$first, $second], $index->byPrefix('village', 'cen', 12));
            self::assertSame([$first, $second], $index->childrenOf('village', 12));
            self::assertSame([$root[9], $county, $subcounty, $parish], $index->ancestorsOf('village', 33));
        }
    }

    /** @test */
    public function numericNamesAndWrongParentScopeCannotCollide(): void
    {
        $countyOne = new County(11, '123', 7);
        $countyTwo = new County(12, '123', 8);
        $index = new LocationIndex([
            7 => new District(7, 'First', [11 => $countyOne]),
            8 => new District(8, 'Second', [12 => $countyTwo]),
        ]);

        self::assertSame([$countyTwo], $index->byName('county', '123', 8));
        self::assertSame([$countyOne], $index->byPrefix('county', '12', 7));
        self::assertSame([], $index->byPrefix('county', '12', 9));
    }
}
