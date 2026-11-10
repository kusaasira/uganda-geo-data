<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Data\JsonDatasetLoader;
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

    private string $sourceDirectory;

    public function __construct(?string $sourceDirectory = null)
    {
        $this->sourceDirectory = $sourceDirectory ?? __DIR__ . '/Data';
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
        foreach ($this->districts as $district) {
            if (strtolower($district->name()) === strtolower($name)) {
                return $district;
            }
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
            array_push($parishes, ...array_values($subCounty->parishes()));
        }
        return $parishes;
    }

    /**
     * @throws ParishNotFoundException
     */
    public function parish(string $name): Parish
    {
        $name = strtolower($name);
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

        throw new VillageNotFoundException();
    }

    /** @return array<int, District> */
    private function generateTree(): array
    {
        /** @var array<int, array<string, string>> $villages */
        $villages = $this->getFileContents($this->sourceDirectory . '/villages.json');

        /** @var array<int, array<string, string>> $parishes */
        $parishes = $this->getFileContents($this->sourceDirectory . '/parishes.json');

        /** @var array<int, array<string, string>> $subCounties */
        $subCounties = $this->getFileContents($this->sourceDirectory . '/sub_counties.json');

        /** @var array<int, array<string, string>> $counties */
        $counties = $this->getFileContents($this->sourceDirectory . '/counties.json');

        /** @var array<int, array<string, string>> $districts */
        $districts = $this->getFileContents($this->sourceDirectory . '/districts.json');

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

    /** @return array<int, array<string, int|string>> */
    private function getFileContents(string $filePath): array
    {
        return (new JsonDatasetLoader())->load($filePath);
    }
}
