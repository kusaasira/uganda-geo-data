<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Data\JsonDatasetLoader;
use Uganda\Exceptions\AmbiguousLocationException;
use Uganda\Exceptions\CountyNotFoundException;
use Uganda\Exceptions\DistrictNotFoundException;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Exceptions\SubCountyNotFoundException;
use Uganda\Exceptions\VillageNotFoundException;
use Uganda\Lookup\LocationIndex;

final class Uganda
{
    /**
     * @var array<int, District>
     */
    private array $districts;

    private ?LocationIndex $locationIndex = null;

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
        return iterator_to_array($this->iterate('county'), false);
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
        return iterator_to_array($this->iterate('subcounty'), false);
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

    /**
     * Enumerate one hierarchy level without allocating a flattened array.
     * Consumers can stream very large village collections directly.
     *
     * @return \Generator<int, object>
     */
    public function iterate(string $level): \Generator
    {
        yield from $this->index()->iterate($level);
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

    /** @return array<int, object> */
    public function searchByPrefix(string $level, string $prefix, ?int $parentId = null, ?int $limit = null): array
    {
        return $this->index()->byPrefix($level, $prefix, $parentId, $limit);
    }

    /** @return array<int, object> */
    public function childrenOf(string $level, int $parentId): array
    {
        return $this->index()->childrenOf($level, $parentId);
    }

    /** @return array<int, object> */
    public function ancestorsOf(string $level, int $id): array
    {
        return $this->index()->ancestorsOf($level, $id);
    }

    private function index(): LocationIndex
    {
        if ($this->locationIndex === null) {
            $this->locationIndex = new LocationIndex($this->districts);
        }

        return $this->locationIndex;
    }

    /**
     * Construct the immutable hierarchy bottom-up, loading one source table
     * at a time. Retain no decoded source arrays beyond their hydration pass.
     *
     * @return array<int, District>
     */
    private function generateTree(): array
    {
        /** @var array<int, array<int, Village>> $mappedVillages */
        $mappedVillages = [];
        $seen = [];
        foreach ($this->getFileContents($this->sourceDirectory . '/villages.json') as $village) {
            $id = (int) $village['id'];
            if (isset($seen[$id])) {
                throw new \UnexpectedValueException('Duplicate village ID: ' . $id);
            }
            $seen[$id] = true;
            $parent = (int) $village['parish'];
            $mappedVillages[$parent][$id] = new Village($id, (string) $village['name'], $parent);
        }
        unset($seen);

        /** @var array<int, array<int, Parish>> $mappedParishes */
        $mappedParishes = [];
        $seen = [];
        foreach ($this->getFileContents($this->sourceDirectory . '/parishes.json') as $parish) {
            $id = (int) $parish['id'];
            if (isset($seen[$id])) {
                throw new \UnexpectedValueException('Duplicate parish ID: ' . $id);
            }
            $seen[$id] = true;
            $parent = (int) $parish['subcounty'];
            $mappedParishes[$parent][$id] = new Parish(
                $id,
                ucfirst((string) $parish['name']),
                $parent,
                $mappedVillages[$id] ?? []
            );
            unset($mappedVillages[$id]);
        }
        $this->assertNoOrphans($mappedVillages, 'village', 'parish');
        unset($mappedVillages, $seen);

        /** @var array<int, array<int, SubCounty>> $mappedSubCounties */
        $mappedSubCounties = [];
        $seen = [];
        foreach ($this->getFileContents($this->sourceDirectory . '/sub_counties.json') as $subCounty) {
            $id = (int) $subCounty['id'];
            if (isset($seen[$id])) {
                throw new \UnexpectedValueException('Duplicate subCounty ID: ' . $id);
            }
            $seen[$id] = true;
            $parent = (int) $subCounty['county'];
            $mappedSubCounties[$parent][$id] = new SubCounty(
                $id,
                ucfirst((string) $subCounty['name']),
                $parent,
                $mappedParishes[$id] ?? []
            );
            unset($mappedParishes[$id]);
        }
        $this->assertNoOrphans($mappedParishes, 'parish', 'subcounty');
        unset($mappedParishes, $seen);

        /** @var array<int, array<int, County>> $mappedCounties */
        $mappedCounties = [];
        $seen = [];
        foreach ($this->getFileContents($this->sourceDirectory . '/counties.json') as $county) {
            $id = (int) $county['id'];
            if (isset($seen[$id])) {
                throw new \UnexpectedValueException('Duplicate county ID: ' . $id);
            }
            $seen[$id] = true;
            $parent = (int) $county['district'];
            $mappedCounties[$parent][$id] = new County(
                $id,
                ucfirst((string) $county['name']),
                $parent,
                $mappedSubCounties[$id] ?? []
            );
            unset($mappedSubCounties[$id]);
        }
        $this->assertNoOrphans($mappedSubCounties, 'subcounty', 'county');
        unset($mappedSubCounties, $seen);

        /** @var array<int, District> $mappedDistricts */
        $mappedDistricts = [];
        $seen = [];
        foreach ($this->getFileContents($this->sourceDirectory . '/districts.json') as $district) {
            $id = (int) $district['id'];
            if (isset($seen[$id])) {
                throw new \UnexpectedValueException('Duplicate district ID: ' . $id);
            }
            $seen[$id] = true;
            $mappedDistricts[$id] = new District(
                $id,
                ucfirst((string) $district['name']),
                $mappedCounties[$id] ?? []
            );
            unset($mappedCounties[$id]);
        }
        $this->assertNoOrphans($mappedCounties, 'county', 'district');

        return $mappedDistricts;
    }

    /**
     * Every decoded child must be attached to exactly one known parent.
     * Numeric source IDs are normalized to native integer keys before
     * attachment, so zero-padded parent strings cannot discard records.
     *
     * @param array<int, array<int, object>> $orphanGroups
     */
    private function assertNoOrphans(array $orphanGroups, string $childLevel, string $parentLevel): void
    {
        foreach ($orphanGroups as $parentId => $children) {
            $childId = array_key_first($children);
            if ($childId !== null) {
                throw new \UnexpectedValueException(sprintf(
                    'Orphan %s record %d: unknown %s %d',
                    $childLevel,
                    $childId,
                    $parentLevel,
                    $parentId
                ));
            }
        }
    }

    /** @return array<int, array<string, int|string>> */
    private function getFileContents(string $filePath): array
    {
        return (new JsonDatasetLoader())->load($filePath);
    }
}
