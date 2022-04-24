<?php

use Uganda\Geo;
use PHPUnit\Framework\TestCase;
use Faker\Factory;
use \Faker\Provider\en_UG\Address;

/**
 * @covers \Uganda\Geo
*/
final class DistrictTest extends TestCase {

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
     * @covers \Uganda\Geo::districts
    */
    public function testUgandaHasDistricts() {
        $district_data = $this->Uganda->districts()->all();
        $data = json_decode($district_data);
        $this->assertGreaterThanOrEqual(135, $data->districts->count);
    } 
    
    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
    */
    public function testDistrictsDoesNotExist() {
        $fake_district = $this->faker->name;
        $district_data = $this->Uganda->districts($fake_district)->all();
        $data = json_decode($district_data);
        $this->assertEquals('District '.$fake_district.' does not exist.', $data->errors->district);
    } 

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
    */
    public function testDistrictHasCounties() {
        $county_data = $this->Uganda->districts($this->district)->counties()->all();
        $data = json_decode($county_data);
        $this->assertGreaterThan(0, $data->counties->count);
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
     * @covers \Uganda\Geo::sub_counties
    */
    public function testDistrictHasSubCounties() {
        $sub_county_data = $this->Uganda->districts($this->district)->counties($this->county)->sub_counties()->all();
        $data = json_decode($sub_county_data);
        $this->assertGreaterThan(0, $data->sub_counties->count);
    }
}
