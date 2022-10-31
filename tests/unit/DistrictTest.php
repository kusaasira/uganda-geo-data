<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Exceptions\CountyNotFoundException;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\SubCountyNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class DistrictTest extends TestCase
{
    private District $district;

    public function setUp(): void
    {
        parent::setUp();

        $village1 = new Village(123, 'New York', 456);
        $village2 = new Village(124, 'Boston', 456);

        $parish = new Parish(456, 'Murica', 789, [
            strtolower($village1->name()) => $village1,
            strtolower($village2->name()) => $village2
        ]);

        $subCounty = new SubCounty(789, 'Earth', 1000, [
            strtolower($parish->name()) => $parish
        ]);

        $county = new County(1000, 'Space', 9999, [
            strtolower($subCounty->name()) => $subCounty
        ]);

        $this->district = new District(9999, 'Everything Else', [
            strtolower($county->name()) => $county
        ]);
    }

    /** @test */
    public function canAccessProperties(): void
    {
        $this->assertSame(9999, $this->district->id());
        $this->assertSame('Everything Else', $this->district->name());
        $this->assertIsArray($this->district->counties());
        $this->assertIsArray($this->district->subCounties());
        $this->assertIsArray($this->district->parishes());
        $this->assertIsArray($this->district->villages());
    }

    /** @test */
    public function canAccessSpecificCounty(): void
    {
        $county = $this->district->county('Space');
        $this->assertSame(1000, $county->id());
        $this->assertSame('Space', $county->name());
        $this->assertSame($this->district->id(), $county->districtId());
    }

    /** @test */
    public function throwsCountyNotFoundExceptionWhenCountyDoesNotExist(): void
    {
        $this->expectException(CountyNotFoundException::class);
        $this->expectExceptionMessage('unable to locate county called Not Space');

        $this->district->county('Not Space');
    }

    /** @test */
    public function canAccessSpecificSubCounty(): void
    {
        $subCounty = $this->district->subCounty('Earth');
        $this->assertSame(789, $subCounty->id());
        $this->assertSame('Earth', $subCounty->name());
    }

    /** @test */
    public function throwsSubCountyNotFoundExceptionWhenSubCountyDoesNotExist(): void
    {
        $this->expectException(SubCountyNotFoundException::class);
        $this->expectExceptionMessage('unable to locate sub county called Moon');

        $this->district->subCounty('Moon');
    }

    /** @test */
    public function canAccessSpecificParish(): void
    {
        $parish = $this->district->parish('Murica');
        $this->assertSame(456, $parish->id());
        $this->assertSame('Murica', $parish->name());
    }

    /** @test */
    public function throwsParishNotFoundExceptionWhenParishDoesNotExist(): void
    {
        $this->expectException(ParishNotFoundException::class);
        $this->expectExceptionMessage('unable to locate parish called Germany');

        $this->district->parish('Germany');
    }

    /** @test */
    public function canAccessSpecificVillage(): void
    {
        $village = $this->district->village('New York');
        $this->assertSame(123, $village->id());
        $this->assertSame('New York', $village->name());
    }

    /** @test */
    public function throwsVillageNotFoundExceptionWhenVillageDoesNotExist(): void
    {
        $this->expectException(VillageNotFoundException::class);
        $this->expectExceptionMessage('unable to locate village called Dallas');

        $this->district->village('Dallas');
    }
}
