<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\SubCountyNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;

final class County
{
    private int $id;

    private string $name;

    private int $districtId;

    /** @var SubCounty[] */
    private array $subCounties;

    /**
     * @param int $id
     * @param string $name
     * @param int $districtId
     * @param SubCounty[] $subCounties
     */
    public function __construct(int $id, string $name, int $districtId, array $subCounties = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->districtId = $districtId;
        $this->subCounties = $subCounties;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function districtId(): int
    {
        return $this->districtId;
    }

    /**
     * @throws SubCountyNotFoundException
     */
    public function subCounty(string $name): SubCounty
    {
        if (!in_array($name, $this->subCounties, true)) {
            throw new SubCountyNotFoundException(sprintf('unable to locate sub county called %s', $name));
        }

        return $this->subCounties[$name];
    }

    /** @return array<int, SubCounty> */
    public function subCounties(): array
    {
        return $this->subCounties;
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

    /** @return array<string, array<int, array<string, array<int, array<string, array<array<string,int|string>>|int|string>>|int|string>>|int|string> */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'districtId' => $this->districtId(),
            'subCounties' => array_map(static function (SubCounty $subCounty) {
                return $subCounty->toArray();
            }, $this->subCounties())
        ];
    }
}
