<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\AmbiguousAdministrativeNameException;
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

    /** @var array<string, array<string, array<int, array{unit: object, parent_id: int|null}>>> */
    private array $nameIndex = [];

    public function __construct()
    {
        $this->districts = $this->generateTree();
        $this->indexNames();
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
        $unit = $this->uniqueNameMatch('district', $name);
        if ($unit instanceof District) {
            return $unit;
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
        $unit = $this->uniqueNameMatch('county', $name);
        if ($unit instanceof County) {
            return $unit;
        }

        throw new CountyNotFoundException();
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
        $unit = $this->uniqueNameMatch('subcounty', $name);
        if ($unit instanceof SubCounty) {
            return $unit;
        }

        throw new SubCountyNotFoundException();
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
        $unit = $this->uniqueNameMatch('parish', $name);
        if ($unit instanceof Parish) {
            return $unit;
        }

        throw new ParishNotFoundException();
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
        $unit = $this->uniqueNameMatch('village', $name);
        if ($unit instanceof Village) {
            return $unit;
        }

        throw new VillageNotFoundException();
    }

    /**
     * Return every matching administrative unit, optionally constrained to its
     * immediate parent. A name is a search term, never an identity.
     *
     * @return array<int, District|County|SubCounty|Parish|Village>
     */
    public function searchByName(string $level, string $name, ?int $parentId = null): array
    {
        if (!in_array($level, ['district', 'county', 'subcounty', 'parish', 'village'], true)) {
            throw new \InvalidArgumentException('Unsupported administrative level: ' . $level);
        }
        $key = strtolower(trim($name));
        if ($key === '') {
            return [];
        }
        $entries = $this->nameIndex[$level][$key] ?? [];
        $matches = [];
        foreach ($entries as $entry) {
            if ($parentId === null || $parentId === $entry['parent_id']) {
                $unit = $entry['unit'];
                if ($unit instanceof District || $unit instanceof County || $unit instanceof SubCounty ||
                    $unit instanceof Parish || $unit instanceof Village) {
                    $matches[] = $unit;
                }
            }
        }
        return $matches;
    }

    /** @return District|County|SubCounty|Parish|Village|null */
    private function uniqueNameMatch(string $level, string $name)
    {
        $matches = $this->searchByName($level, $name);
        if (count($matches) > 1) {
            throw new AmbiguousAdministrativeNameException(
                sprintf('Ambiguous %s name "%s" matched %d records; use a scoped search or canonical ID',
                    $level, $name, count($matches))
            );
        }
        return $matches[0] ?? null;
    }

    private function indexNames(): void
    {
        foreach ($this->districts() as $unit) {
            $this->indexUnit('district', $unit, null);
        }
        foreach ($this->counties() as $unit) {
            $this->indexUnit('county', $unit, $unit->districtId());
        }
        foreach ($this->subCounties() as $unit) {
            $this->indexUnit('subcounty', $unit, $unit->countyId());
        }
        foreach ($this->parishes() as $unit) {
            $this->indexUnit('parish', $unit, $unit->subCountyId());
        }
        foreach ($this->villages() as $unit) {
            $this->indexUnit('village', $unit, $unit->parishId());
        }
    }

    /** @param District|County|SubCounty|Parish|Village $unit */
    private function indexUnit(string $level, $unit, ?int $parentId): void
    {
        $key = strtolower(trim($unit->name()));
        $this->nameIndex[$level][$key][] = ['unit' => $unit, 'parent_id' => $parentId];
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
