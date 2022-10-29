<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\CountyNotFoundException;
use Uganda\Exceptions\DistrictNotFoundException;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\SubCountyNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;

final class Uganda
{
    /**
     * @var array<int, District>
     */
    private array $districts;

    public function __construct()
    {
        $this->districts = [];
    }

    /**
     * @return array<int, District>
     */
    public function districts(): array
    {
        return $this->districts;
    }

    public function district(string $name): District
    {
        if (!in_array($name, $this->districts, true)) {
            throw new DistrictNotFoundException();
        }

        return $this->districts[$name];
    }

    /**
     * @return array<int, County>
     */
    public function counties(): array
    {
        $counties = [];
        foreach ($this->districts() as $district) {
            $districtCounties = $district->counties();
            $counties = array_push(...$districtCounties);
        }
        return $counties;
    }

    /**
     * @throws CountyNotFoundException
     */
    public function county(string $name): County
    {
        foreach ($this->districts() as $district) {
            try {
                return $district->county($name);
            } catch (CountyNotFoundException $exception) {
                continue;
            }
        }

        throw new CountyNotFoundException();
    }

    /** @return array<int, SubCounty> */
    public function subCounties(): array
    {
        /** @var County[] $counties */
        $counties = $this->counties();
        $subCounties = [];
        foreach ($counties as $county) {
            $countySubCounties = $county->subCounties();
            $subCounties = array_push(...$countySubCounties);
        }
        return $subCounties;
    }

    /**
     * @throws SubCountyNotFoundException
     */
    public function subCounty(string $name): SubCounty
    {
        foreach ($this->counties() as $county) {
            try {
                return $county->subCounty($name);
            } catch (SubCountyNotFoundException $exception) {
                continue;
            }
        }

        throw new SubCountyNotFoundException();
    }

    /** @return array<int, Parish> */
    public function parishes(): array
    {
        $subCounties = $this->subCounties();
        $parishes = [];
        foreach ($subCounties as $subCounty) {
            $subCountyParishes = $subCounty->parishes();
            $parishes = array_push(...$subCountyParishes);
        }
        return $parishes;
    }

    public function parish(string $name): Parish
    {
        foreach ($this->subCounties() as $subCounty) {
            try {
                return $subCounty->parish($name);
            } catch (ParishNotFoundException $exception) {
                continue;
            }
        }

        throw new ParishNotFoundException();
    }

    /** @return array<int, Village> */
    public function villages(): array
    {
        $parishes = $this->parishes();
        $villages = [];
        foreach ($parishes as $parish) {
            $parishVillages = $parish->parishes();
            $villages = array_push(...$parishVillages);
        }
        return $villages;
    }

    /**
     * @throws VillageNotFoundException
     */
    public function village(string $name): Village
    {
        foreach ($this->parishes() as $parish) {
            try {
                return $parish->village($name);
            } catch (VillageNotFoundException $exception) {
                continue;
            }
        }

        throw new VillageNotFoundException();
    }
}
