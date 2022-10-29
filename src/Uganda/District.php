<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\CountyNotFoundException;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\SubCountyNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;

final class District
{
    private int $id;

    private string $name;

    /** @var array<int, County> */
    private $counties;

    /**
     * @param int $id
     * @param string $name
     * @param County[] $counties
     */
    public function __construct(int $id, string $name, array $counties = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->counties = $counties;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, County>
     */
    public function counties(): array
    {
        return $this->counties;
    }

    /**
     * @throws CountyNotFoundException
     */
    public function county(string $name): County
    {
        if (!in_array($name, $this->counties, true)) {
            throw new CountyNotFoundException(sprintf('unable to locate county called %s', $name));
        }

        return $this->counties[$name];
    }

    /** @return array<int, SubCounty> */
    public function subCounties(): array
    {
        $subCounties = [];
        foreach ($this->counties() as $county) {
            array_push($subCounties, ...$county->subCounties());
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
        $parishes = [];
        foreach ($this->subCounties() as $subCounty) {
            array_push($parishes, ...$subCounty->parishes());
        }
        return $parishes;
    }

    /**
     * @throws ParishNotFoundException
     */
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
        $villages = [];
        foreach ($this->parishes() as $parish) {
            array_push($villages, ...$parish->villages());
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
