<?php

use Uganda\Geo;
use PHPUnit\Framework\TestCase;
use Faker\Factory;
use \Faker\Provider\en_UG\Address;

/**
 * @covers \Uganda\Geo
*/
final class SubCountyTest extends TestCase {

    public function setUp() : void
    {
        $this->Uganda = new Geo();
        $this->faker = Factory::create();
        $this->faker->addProvider(new Address($this->faker));

        $this->district = $this->faker->district();
        $counties = $this->Uganda->districts($this->district)->counties()->all();
        $this->county = json_decode($counties)->counties->data[0]->name;
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::sub_counties
    */
    public function testUgandaHasSubCounties() {
        $sub_county_data = $this->Uganda->sub_counties()->all();
        $data = json_decode($sub_county_data);
        $this->assertGreaterThanOrEqual(2120, $data->sub_counties->count);
    } 
    
    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
     * @covers \Uganda\Geo::counties
     * @covers \Uganda\Geo::sub_counties
    */
    public function testSubCountiesInNonExistentCounty() {
        $fake_county = $this->faker->name;
        $district_data = $this
                            ->Uganda
                            ->districts($this->district)
                            ->counties($fake_county)
                            ->sub_counties()
                            ->all();
        $data = json_decode($district_data);
        $this->assertEquals('County '.$fake_county.' does not exist.', $data->errors->county);
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
     * @covers \Uganda\Geo::counties
     * @covers \Uganda\Geo::sub_counties
    */
    public function testDistrictHasSubCounties() {
        $sub_counties = $this->Uganda->districts($this->district)->counties($this->county)->sub_counties()->all();
        $sub_county_data = json_decode($sub_counties);

        $this->assertGreaterThan(0, $sub_county_data->sub_counties->count);
    }
}
