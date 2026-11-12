<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uganda\District;
use Uganda\Uganda;

final class PrefixSearchIntegrationTest extends TestCase
{
    /** @test */
    public function prefixSearchFindsTheSameRealDistrictIdentityAsExactSearch(): void
    {
        $geo = new Uganda();
        $district = $geo->district('Mukono');
        $matches = $geo->searchByPrefix('district', 'Muko');

        self::assertContains($district, $matches);
        foreach ($matches as $candidate) {
            self::assertInstanceOf(District::class, $candidate);
            self::assertSame($candidate, $geo->findById('district', $candidate->id()));
        }
    }

    /** @test */
    public function aOneResultLimitDoesNotChangeExactSearchResults(): void
    {
        $geo = new Uganda();
        $prefix = $geo->searchByPrefix('district', 'Muko', null, 1);

        self::assertCount(1, $prefix);
        self::assertSame($geo->district('Mukono'), $prefix[0]);
        self::assertSame($geo->district('Mukono'), $geo->findById('district', $prefix[0]->id()));
    }
}
