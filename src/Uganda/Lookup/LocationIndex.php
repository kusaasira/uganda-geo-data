<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;
use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;

final class LocationIndex
{
    /** @var array<string, array<int, object>> */
    private array $byId = [];

    /** @var array<string, array<string, array<int, int>>> */
    private array $byName = [];

    /** @var array<string, array<int, int|null>> */
    private array $parentById = [];

    /** @var array<string, array<int, string>> */
    private array $sortedNameKeys = [];

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

    /**
     * Return all matching records using a lazily sorted name index.
     * First query: O(U log U), subsequent queries: O(log U + M) for
     * U distinct names and M candidate matches before parent filtering.
     *
     * @return array<int, object>
     */
    public function byPrefix(string $level, string $prefix, ?int $parentId = null, ?int $limit = null): array
    {
        $this->assertLevel($level);
        $prefix = strtolower(trim($prefix));
        if ($prefix === '') {
            throw new InvalidArgumentException('A nonempty location name prefix is required');
        }
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('Prefix result limit must be positive');
        }

        if (!isset($this->sortedNameKeys[$level])) {
            $names = array_map('strval', array_keys($this->byName[$level] ?? []));
            sort($names, SORT_STRING);
            $this->sortedNameKeys[$level] = $names;
        }
        $names = $this->sortedNameKeys[$level];
        $low = 0;
        $high = count($names);
        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            if (strcmp($names[$middle], $prefix) < 0) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        $result = [];
        for ($i = $low, $count = count($names); $i < $count; $i++) {
            if (!str_starts_with($names[$i], $prefix)) {
                break;
            }
            foreach ($this->byName[$level][$names[$i]] as $id) {
                if ($parentId !== null && ($this->parentById[$level][$id] ?? null) !== $parentId) {
                    continue;
                }
                $result[] = $this->byId[$level][$id];
                if ($limit !== null && count($result) >= $limit) {
                    return $result;
                }
            }
        }
        return $result;
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

    /**
     * Read existing child collections directly from the parent model.
     * This avoids constructing a second parent-to-children index.
     *
     * @return array<int, object>
     */
    public function childrenOf(string $level, int $parentId): array
    {
        $this->assertLevel($level);
        if ($level === 'district') {
            throw new InvalidArgumentException('Districts have no administrative parent in this dataset');
        }

        if ($level === 'county') {
            $parent = $this->byId('district', $parentId);
            return $parent instanceof District ? array_values($parent->counties()) : [];
        }
        if ($level === 'subcounty') {
            $parent = $this->byId('county', $parentId);
            return $parent instanceof County ? array_values($parent->subCounties()) : [];
        }
        if ($level === 'parish') {
            $parent = $this->byId('subcounty', $parentId);
            return $parent instanceof SubCounty ? array_values($parent->parishes()) : [];
        }

        $parent = $this->byId('parish', $parentId);
        return $parent instanceof Parish ? array_values($parent->villages()) : [];
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
