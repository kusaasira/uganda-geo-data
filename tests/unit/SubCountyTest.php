<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class SubCountyTest extends TestCase
{
    private SubCounty $subCounty;

    public function setUp(): void
    {
        parent::setUp();

        $village1 = new Village(123, 'New York', 456);
        $village2 = new Village(124, 'Boston', 456);

        $parish = new Parish(456, 'Murica', 789, [
            strtolower($village1->name()) => $village1,
            strtolower($village2->name()) => $village2
        ]);

        $this->subCounty = new SubCounty(789, 'Earth', 1000, [
            strtolower($parish->name()) => $parish
        ]);
    }

    /** @test */
    public function canAccessProperties(): void
    {
        $this->assertSame(789, $this->subCounty->id());
        $this->assertSame('Earth', $this->subCounty->name());
        $this->assertSame(1000, $this->subCounty->countyId());
        $this->assertIsArray($this->subCounty->parishes());
        $this->assertIsArray($this->subCounty->villages());
    }

    /** @test */
    public function canAccessSpecificParish(): void
    {
        $parish = $this->subCounty->parish('Murica');
        $this->assertSame(456, $parish->id());
        $this->assertSame('Murica', $parish->name());
        $this->assertSame($this->subCounty->id(), $parish->subCountyId());
    }

    /** @test */
    public function throwsParishNotFoundExceptionWhenParishDoesNotExist(): void
    {
        $this->expectException(ParishNotFoundException::class);
        $this->expectExceptionMessage('unable to locate parish called Germany');

        $this->subCounty->parish('Germany');
    }

    /** @test */
    public function canAccessSpecificVillage(): void
    {
        $village = $this->subCounty->village('New York');
        $this->assertSame(123, $village->id());
        $this->assertSame('New York', $village->name());
    }

    /** @test */
    public function throwsVillageNotFoundExceptionWhenVillageDoesNotExist(): void
    {
        $this->expectException(VillageNotFoundException::class);
        $this->expectExceptionMessage('unable to locate village called Dallas');

        $this->subCounty->village('Dallas');
    }
}
