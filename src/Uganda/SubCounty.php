<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;

final class SubCounty
{
    private int $id;

    private string $name;

    private int $countyId;

    /** @var array<int|string, Parish> */
    private array $parishes;

    /**
     * @param int $id
     * @param string $name
     * @param array<int|string, Parish> $parishes
     */
    public function __construct(int $id, string $name, int $countyId, array $parishes = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->countyId = $countyId;
        $this->parishes = $parishes;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function countyId(): int
    {
        return $this->countyId;
    }

    /** @return array<int|string, Parish> */
    public function parishes(): array
    {
        return $this->parishes;
    }

    public function parish(string $name): Parish
    {
        foreach ($this->parishes as $child) {
            if (strtolower($child->name()) === strtolower($name)) {
                return $child;
            }
        }

        throw new ParishNotFoundException(sprintf('unable to locate parish called %s', $name));
    }

    /** @return array<int, Village> */
    public function villages(): array
    {
        $villages = [];
        foreach ($this->parishes() as $parish) {
            array_push($villages, ...array_values($parish->villages()));
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

        throw new VillageNotFoundException(sprintf('unable to locate village called %s', $name));
    }

    /** @return array{id: int, name: string, countyId: int, parishes: array<int|string, array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'countyId' => $this->countyId(),
            'parishes' => array_map(static function (Parish $parish) {
                return $parish->toArray();
            }, $this->parishes())
        ];
    }
}
