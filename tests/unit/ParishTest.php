<?php

use Uganda\Geo;
use PHPUnit\Framework\TestCase;
use Faker\Factory;
use \Faker\Provider\en_UG\Address;

/**
 * @covers \Uganda\Geo
*/
final class ParishTest extends TestCase {

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
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::parishes
    */
    public function testUgandaHasParishes() {
        $parish_data = $this->Uganda->parishes()->all();
        $data = json_decode($parish_data);
        $this->assertGreaterThanOrEqual(10365, $data->parishes->count);
    } 
    
    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
     * @covers \Uganda\Geo::counties
     * @covers \Uganda\Geo::sub_counties
     * @covers \Uganda\Geo::parishes
    */
    public function testParishInNonExistentSubCounty() {
        $fake_sub_county = $this->faker->name;
        $district_data = $this
                            ->Uganda
                            ->districts($this->faker->district())
                            ->counties($this->county)
                            ->sub_counties($fake_sub_county)
                            ->parishes()
                            ->all();
        $data = json_decode($district_data);
        $this->assertEquals('Sub county '.$fake_sub_county.' does not exist.', $data->errors->sub_county);
    }

    /**
     * @runInSeparateProcess
     * @covers \Uganda\Geo::districts
     * @covers \Uganda\Geo::counties
     * @covers \Uganda\Geo::sub_counties
     * @covers \Uganda\Geo::parishes
    */
    public function testDistrictHasParishes() {
        $parishes = $this
                        ->Uganda
                        ->districts($this->district)
                        ->counties($this->county)
                        ->sub_counties($this->sub_county)
                        ->parishes()->all();
        $parish_data = json_decode($parishes);

        $this->assertGreaterThan(0, $parish_data->parishes->count);
    }
}
