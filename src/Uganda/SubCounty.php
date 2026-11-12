<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\AmbiguousLocationException;

use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;

final class SubCounty implements \JsonSerializable
{
    use SerializesLocation;

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
        $matches = [];
        foreach ($this->parishes() as $candidate) {
            if (strtolower(trim($candidate->name())) === strtolower(trim($name))) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple parish records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0])) {
            return $matches[0];
        }

        throw new ParishNotFoundException(sprintf('unable to locate parish called %s', $name));
    }

    /** @return array<int, Village> */
    public function villages(): array
    {
        $villages = [];
        foreach ($this->parishes as $parish) {
            foreach ($parish->villages() as $village) {
                $villages[] = $village;
            }
        }
        return $villages;
    }

    /**
     * @throws VillageNotFoundException
     */
    public function village(string $name): Village
    {
        $matches = [];
        foreach ($this->villages() as $candidate) {
            if (strtolower(trim($candidate->name())) === strtolower(trim($name))) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple village records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0])) {
            return $matches[0];
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
