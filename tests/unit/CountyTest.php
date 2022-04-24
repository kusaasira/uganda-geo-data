<?php

use Uganda\Geo;
use PHPUnit\Framework\TestCase;
use Faker\Factory;
use \Faker\Provider\en_UG\Address;

/**
 * @covers \Uganda\Geo
*/
final class CountyTest extends TestCase {

    public function setUp() : void
    {
        $this->Uganda = new Geo();
        $this->faker = Factory::create();
        $this->faker->addProvider(new Address($this->faker));
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::counties
    */
    public function testUgandaHasCounties() {
        $district_data = $this->Uganda->counties()->all();
        $data = json_decode($district_data);
        $this->assertGreaterThanOrEqual(303, $data->counties->count);
    }
}
