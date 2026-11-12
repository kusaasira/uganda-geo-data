<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;
use Uganda\District;

final class LocationIndex
{
    /** @var array<string, array<int, object>> */
    private array $byId = [];

    /** @var array<string, array<string, array<int, int>>> */
    private array $byName = [];

    /** @var array<string, array<int, int|null>> */
    private array $parentById = [];

    /** @var array<string, array<int, array<int, int>>> */
    private array $childrenByParent = [];

    /** @var array<string, bool> */
    private array $indexedChildLevels = [];

    /** @param array<int, District> $districts */
    public function __construct(array $districts)
    {
        foreach ($districts as $district) {
            $this->add('district', $district->id(), $district->name(), null, $district);
            foreach ($district->counties() as $county) {
                $this->add('county', $county->id(), $county->name(), $county->districtId(), $county);
                foreach ($county->subCounties() as $subcounty) {
                    $this->add('subcounty', $subcounty->id(), $subcounty->name(), $subcounty->countyId(), $subcounty);
                    foreach ($subcounty->parishes() as $parish) {
                        $this->add('parish', $parish->id(), $parish->name(), $parish->subCountyId(), $parish);
                        foreach ($parish->villages() as $village) {
                            $this->add('village', $village->id(), $village->name(), $village->parishId(), $village);
                        }
                    }
                }
            }
        }
    }

    private function add(string $level, int $id, string $name, ?int $parentId, object $unit): void
    {
        if (isset($this->byId[$level][$id])) {
            throw new InvalidArgumentException("Duplicate $level identifier $id");
        }
        $this->byId[$level][$id] = $unit;
        $this->byName[$level][strtolower(trim($name))][] = $id;
        $this->parentById[$level][$id] = $parentId;
    }

    public function byId(string $level, int $id): ?object
    {
        $this->assertLevel($level);
        return $this->byId[$level][$id] ?? null;
    }

    /** @return array<int, object> */
    public function byName(string $level, string $name, ?int $parentId = null): array
    {
        $this->assertLevel($level);
        $result = [];
        foreach ($this->byName[$level][strtolower(trim($name))] ?? [] as $id) {
            if ($parentId === null || ($this->parentById[$level][$id] ?? null) === $parentId) {
                $result[] = $this->byId[$level][$id];
            }
        }

        return $result;
    }

    /** @return array<int, object> */
    public function childrenOf(string $level, int $parentId): array
    {
        $this->assertLevel($level);
        if ($level === 'district') {
            throw new InvalidArgumentException('Districts have no administrative parent in this dataset');
        }

        // Only index the requested level. A lookup for counties must not
        // allocate parent-child maps for every parish and village.
        if (!isset($this->indexedChildLevels[$level])) {
            foreach ($this->parentById[$level] ?? [] as $childId => $idOfParent) {
                if ($idOfParent !== null) {
                    $this->childrenByParent[$level][$idOfParent][] = $childId;
                }
            }
            $this->indexedChildLevels[$level] = true;
        }

        $children = [];
        foreach ($this->childrenByParent[$level][$parentId] ?? [] as $id) {
            $children[] = $this->byId[$level][$id];
        }

        return $children;
    }

    /**
     * Return the ancestry in root-to-parent order without including the unit itself.
     *
     * @return array<int, object>
     */
    public function ancestorsOf(string $level, int $id): array
    {
        $this->assertLevel($level);
        if (!isset($this->byId[$level][$id])) {
            return [];
        }

        $parentLevels = [
            'county' => 'district',
            'subcounty' => 'county',
            'parish' => 'subcounty',
            'village' => 'parish',
        ];
        $ancestors = [];

        while (isset($parentLevels[$level])) {
            $parentId = $this->parentById[$level][$id] ?? null;
            if ($parentId === null) {
                throw new InvalidArgumentException('Missing administrative parent for ' . $level . ' ' . $id);
            }

            $level = $parentLevels[$level];
            if (!isset($this->byId[$level][$parentId])) {
                throw new InvalidArgumentException('Unknown administrative parent for ' . $level . ' ' . $parentId);
            }
            $ancestors[] = $this->byId[$level][$parentId];
            $id = $parentId;
        }

        return array_reverse($ancestors);
    }

    private function assertLevel(string $level): void
    {
        if (!in_array($level, ['district', 'county', 'subcounty', 'parish', 'village'], true)) {
            throw new InvalidArgumentException('Unsupported administrative level: ' . $level);
        }
    }
}
