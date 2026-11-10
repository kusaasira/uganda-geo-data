<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\AmbiguousLocationException;
use Uganda\Exceptions\CountyNotFoundException;
use Uganda\Lookup\LocationIndex;
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

    private ?LocationIndex $locationIndex = null;

    public function __construct()
    {
        $this->districts = $this->generateTree();
    }

    /**
     * @return array<int, District>
     */
    public function districts(): array
    {
        return $this->districts;
    }

    /**
     * @throws DistrictNotFoundException
     */
    public function district(string $name): District
    {
        $matches = $this->searchByName('district', $name);
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple district records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0]) && $matches[0] instanceof District) {
            return $matches[0];
        }

        throw new DistrictNotFoundException(sprintf('unable to locate district called %s', $name));
    }

    /**
     * @return array<int, County>
     */
    public function counties(): array
    {
        $counties = [];
        foreach ($this->districts() as $district) {
            array_push($counties, ...array_values($district->counties()));
        }
        return $counties;
    }

    /**
     * @throws CountyNotFoundException
     */
    public function county(string $name): County
    {
        $matches = $this->searchByName('county', $name);
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple county records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0]) && $matches[0] instanceof County) {
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
        $matches = $this->searchByName('subcounty', $name);
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple sub county records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0]) && $matches[0] instanceof SubCounty) {
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
        $matches = $this->searchByName('parish', $name);
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple parish records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0]) && $matches[0] instanceof Parish) {
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
        $matches = $this->searchByName('village', $name);
        if (count($matches) > 1) {
            throw new AmbiguousLocationException(sprintf(
                'multiple village records match %s; use an ID or parent-scoped lookup',
                $name
            ));
        }

        if (isset($matches[0]) && $matches[0] instanceof Village) {
            return $matches[0];
        }

        throw new VillageNotFoundException(sprintf('unable to locate village called %s', $name));
    }

    public function findById(string $level, int $id): ?object
    {
        return $this->index()->byId($level, $id);
    }

    /** @return array<int, object> */
    public function searchByName(string $level, string $name, ?int $parentId = null): array
    {
        return $this->index()->byName($level, $name, $parentId);
    }

    private function index(): LocationIndex
    {
        if ($this->locationIndex === null) {
            $this->locationIndex = new LocationIndex($this->districts);
        }

        return $this->locationIndex;
    }

    /** @return array<int, District> */
    private function generateTree(): array
    {
        /** @var array<int, array<string, string>> $villages */
        $villages = $this->getFileContents(__DIR__ . '/Data/villages.json');

        /** @var array<int, array<string, string>> $parishes */
        $parishes = $this->getFileContents(__DIR__ . '/Data/parishes.json');

        /** @var array<int, array<string, string>> $subCounties */
        $subCounties = $this->getFileContents(__DIR__ . '/Data/sub_counties.json');

        /** @var array<int, array<string, string>> $counties */
        $counties = $this->getFileContents(__DIR__ . '/Data/counties.json');

        /** @var array<int, array<string, string>> $districts */
        $districts = $this->getFileContents(__DIR__ . '/Data/districts.json');

        // Keep the source identity distinct from the display name.
        // Reject duplicate IDs rather than silently overwriting records.
        $seenIds = [];
        /** @var array<int, array<int, Village>> $mappedVillages */
        $mappedVillages = [];
        foreach ($villages as $village) {
            $id = (int) $village['id'];
            if (isset($seenIds['village'][$id])) {
                throw new \UnexpectedValueException('Duplicate village ID: ' . $id);
            }
            $seenIds['village'][$id] = true;
            $mappedVillages[$village['parish']][$id] = new Village(
                (int) $village['id'],
                $village['name'],
                (int) $village['parish']
            );
        }

        /** @var array<int, array<int, Parish>> $mappedParishes */
        $mappedParishes = [];
        foreach ($parishes as $parish) {
            $id = (int) $parish['id'];
            if (isset($seenIds['parish'][$id])) {
                throw new \UnexpectedValueException('Duplicate parish ID: ' . $id);
            }
            $seenIds['parish'][$id] = true;
            $mappedParishes[$parish['subcounty']][$id] = new Parish(
                (int) $parish['id'],
                ucfirst($parish['name']),
                (int) $parish['subcounty'],
                $mappedVillages[$parish['id']] ?? []
            );
        }

        /** @var array<int, array<int, SubCounty>> $mappedSubCounties */
        $mappedSubCounties = [];
        foreach ($subCounties as $subCounty) {
            $id = (int) $subCounty['id'];
            if (isset($seenIds['subCounty'][$id])) {
                throw new \UnexpectedValueException('Duplicate subCounty ID: ' . $id);
            }
            $seenIds['subCounty'][$id] = true;
            $mappedSubCounties[$subCounty['county']][$id] = new SubCounty(
                (int) $subCounty['id'],
                ucfirst($subCounty['name']),
                (int) $subCounty['county'],
                $mappedParishes[$subCounty['id']] ?? []
            );
        }

        /** @var array<int, array<int, County>> $mappedCounties */
        $mappedCounties = [];
        foreach ($counties as $county) {
            $id = (int) $county['id'];
            if (isset($seenIds['county'][$id])) {
                throw new \UnexpectedValueException('Duplicate county ID: ' . $id);
            }
            $seenIds['county'][$id] = true;
            $mappedCounties[$county['district']][$id] = new County(
                (int) $county['id'],
                ucfirst($county['name']),
                (int) $county['district'],
                $mappedSubCounties[$county['id']] ?? []
            );
        }

        /** @var array<int, District> $mappedDistrict */
        $mappedDistrict = [];
        foreach ($districts as $district) {
            $id = (int) $district['id'];
            if (isset($seenIds['district'][$id])) {
                throw new \UnexpectedValueException('Duplicate district ID: ' . $id);
            }
            $seenIds['district'][$id] = true;
            $mappedDistrict[$id] = new District(
                (int) $district['id'],
                ucfirst($district['name']),
                $mappedCounties[$district['id']] ?? []
            );
        }

        return $mappedDistrict;
    }

    /** @return array<string, string> */
    private function getFileContents(string $filePath): array
    {
        try {
            /** @var array<string, string> $response */
            $response = (array) json_decode(
                (string) file_get_contents($filePath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $jsonException) {
            return [];
        }

        return $response;
    }
}
