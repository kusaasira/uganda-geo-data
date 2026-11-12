<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\AmbiguousLocationException;

use Uganda\Exceptions\CountyNotFoundException;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\SubCountyNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;

final class District implements \JsonSerializable
{
    use SerializesLocation;

    private int $id;

    private string $name;

    /** @var array<int|string, County> */
    private $counties;

    /**
     * @param int $id
     * @param string $name
     * @param array<int|string, County> $counties
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
     * @return array<int|string, County>
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
        $matches = [];
        foreach ($this->counties() as $candidate) {
            if (strtolower(trim($candidate->name())) === strtolower(trim($name))) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple county records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0])) {
            return $matches[0];
        }

        throw new CountyNotFoundException(sprintf('unable to locate county called %s', $name));
    }

    /** @return array<int, SubCounty> */
    public function subCounties(): array
    {
        $subCounties = [];
        foreach ($this->counties() as $county) {
            array_push($subCounties, ...array_values($county->subCounties()));
        }
        return $subCounties;
    }

    /**
     * @throws SubCountyNotFoundException
     */
    public function subCounty(string $name): SubCounty
    {
        $matches = [];
        foreach ($this->subCounties() as $candidate) {
            if (strtolower(trim($candidate->name())) === strtolower(trim($name))) {
                $matches[] = $candidate;
            }
        }
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple sub county records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0])) {
            return $matches[0];
        }

        throw new SubCountyNotFoundException(sprintf('unable to locate sub county called %s', $name));
    }

    /** @return array<int, Parish> */
    public function parishes(): array
    {
        $parishes = [];
        foreach ($this->subCounties() as $subCounty) {
            array_push($parishes, ...array_values($subCounty->parishes()));
        }
        return $parishes;
    }

    /**
     * @throws ParishNotFoundException
     */
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

    /** @return array{id: int, name: string, counties: array<int|string, array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'counties' => array_map(static function (County $county) {
                return $county->toArray();
            }, $this->counties())
        ];
    }
}
