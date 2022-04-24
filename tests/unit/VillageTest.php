<?php

use Uganda\Geo;
use PHPUnit\Framework\TestCase;
use Faker\Factory;
use \Faker\Provider\en_UG\Address;

/**
 * @covers \Uganda\Geo
*/
final class VillageTest extends TestCase {

    public function setUp() : void
    {
        $this->Uganda = new Geo();
        $this->faker = Factory::create();
        $this->faker->addProvider(new Address($this->faker));
        
        $this->district = $this->faker->district();
        
        $counties = $this->Uganda->districts($this->district)->counties()->all();
        $this->county = json_decode($counties)->counties->data[0]->name;

        $sub_counties = $this->Uganda->districts($this->district)->counties($this->county)->sub_counties()->all();
        $this->sub_county = json_decode($sub_counties)->sub_counties->data[0]->name;

        $parishes = $this->Uganda->districts($this->district)->counties($this->county)->sub_counties($this->sub_county)->parishes()->all();
        $this->parish = json_decode($parishes)->parishes->data[0]->name;
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::villages
    */
    public function testUgandaHasVillages() {
        $village_data = $this->Uganda->villages()->all();
        $data = json_decode($village_data);
        $this->assertGreaterThanOrEqual(71250, $data->villages->count);
    } 
    
    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
     * @covers \Uganda\Geo::counties
     * @covers \Uganda\Geo::sub_counties
     * @covers \Uganda\Geo::parishes
     * @covers \Uganda\Geo::villages
    */
    public function testVillageInNonExistentParish() {
        $fake_parish = $this->faker->name;
        $district_data = $this
                            ->Uganda
                            ->districts($this->district)
                            ->counties($this->county)
                            ->sub_counties($this->sub_county)
                            ->parishes($fake_parish)
                            ->villages()
                            ->all();
        $data = json_decode($district_data);
        $this->assertEquals('Parish '.$fake_parish.' does not exist.', $data->errors->parish);
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
     * @covers \Uganda\Geo::counties
     * @covers \Uganda\Geo::sub_counties
     * @covers \Uganda\Geo::parishes
     * @covers \Uganda\Geo::villages
    */
    public function testDistrictHasVillages() {
        $villages = $this
                        ->Uganda
                        ->districts($this->district)
                        ->counties($this->county)
                        ->sub_counties($this->sub_county)
                        ->parishes($this->parish)
                        ->villages()->all();
        $village_data = json_decode($villages);

        $this->assertGreaterThan(0, $village_data->villages->count);
    }
}
