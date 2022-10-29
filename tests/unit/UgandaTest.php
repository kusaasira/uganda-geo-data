<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\District;
use Uganda\Exceptions\DistrictNotFoundException;
use Uganda\Uganda;

final class UgandaTest extends TestCase
{
    private Uganda $uganda;

    public function setUp(): void
    {
        parent::setUp();
        $this->uganda = new Uganda();
    }

    /** @test */
    public function canAccessDistricts(): void
    {
        $this->assertIsArray($this->uganda->districts());
        $this->assertNotEmpty($this->uganda->districts());
    }

    /** @test */
    public function canAccessSpecificDistrict(): void
    {
        $district = new District(126, "Bukomansimbi", []);
        $this->assertEquals($this->uganda->district("Bukomansimbi")->id(), $district->id());
        $this->assertEquals($this->uganda->district("Bukomansimbi")->name(), $district->name());
        $this->assertIsArray($this->uganda->district("Bukomansimbi")->toArray());
    }

    /** @test */
    public function throwsDistrictNotFoundExceptionWhenDistrictDoesNotExist(): void
    {
        $this->expectException(DistrictNotFoundException::class);
        $this->expectExceptionMessage('unable to locate district called New York');

        $this->uganda->district('New York');
    }
}
