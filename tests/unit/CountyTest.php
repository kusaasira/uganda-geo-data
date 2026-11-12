<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\SubCountyNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class CountyTest extends TestCase
{
    private County $county;

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

        $this->county = new County(1000, 'Space', 9999, [
            strtolower($subCounty->name()) => $subCounty
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function canAccessProperties(): void
    {
        $this->assertSame(1000, $this->county->id());
        $this->assertSame('Space', $this->county->name());
        $this->assertSame(9999, $this->county->districtId());
        $this->assertIsArray($this->county->subCounties());
        $this->assertIsArray($this->county->parishes());
        $this->assertIsArray($this->county->villages());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function canAccessSpecificSubCounty(): void
    {
        $subCounty = $this->county->subCounty('Earth');
        $this->assertSame(789, $subCounty->id());
        $this->assertSame('Earth', $subCounty->name());
        $this->assertSame($this->county->id(), $subCounty->countyId());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throwsSubCountyNotFoundExceptionWhenSubCountyDoesNotExist(): void
    {
        $this->expectException(SubCountyNotFoundException::class);
        $this->expectExceptionMessage('unable to locate sub county called Moon');

        $this->county->subCounty('Moon');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function canAccessSpecificParish(): void
    {
        $parish = $this->county->parish('Murica');
        $this->assertSame(456, $parish->id());
        $this->assertSame('Murica', $parish->name());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throwsParishNotFoundExceptionWhenParishDoesNotExist(): void
    {
        $this->expectException(ParishNotFoundException::class);
        $this->expectExceptionMessage('unable to locate parish called Germany');

        $this->county->parish('Germany');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function canAccessSpecificVillage(): void
    {
        $village = $this->county->village('New York');
        $this->assertSame(123, $village->id());
        $this->assertSame('New York', $village->name());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throwsVillageNotFoundExceptionWhenVillageDoesNotExist(): void
    {
        $this->expectException(VillageNotFoundException::class);
        $this->expectExceptionMessage('unable to locate village called Dallas');

        $this->county->village('Dallas');
    }
}
