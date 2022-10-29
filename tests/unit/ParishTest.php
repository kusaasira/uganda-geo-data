<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Exceptions\VillageNotFoundException;
use Uganda\Parish;
use Uganda\Village;

final class ParishTest extends TestCase
{
    private Parish $parish;

    public function setUp(): void
    {
        parent::setUp();

        $village1 = new Village(123, 'New York', 456);
        $village2 = new Village(124, 'Boston', 456);
        $this->parish = new Parish(456, 'Murica', 789, [
            strtolower($village1->name()) => $village1,
            strtolower($village2->name()) => $village2
        ]);
    }

    /** @test */
    public function canAccessProperties(): void
    {
        $this->assertSame(456, $this->parish->id());
        $this->assertSame('Murica', $this->parish->name());
        $this->assertSame(789, $this->parish->subCountyId());
        $this->assertIsArray($this->parish->villages());
        $this->assertCount(2, $this->parish->villages());
    }

    /** @test */
    public function canAccessSpecificVillage(): void
    {
        $village = $this->parish->village('Boston');
        $this->assertSame('Boston', $village->name());
    }

    /** @test */
    public function throwsVillageNotFoundExceptionWhenVillageDoesNotExist(): void
    {
        $this->expectException(VillageNotFoundException::class);
        $this->expectExceptionMessage('unable to locate village called Dallas');

        $this->parish->village('Dallas');
    }
}
