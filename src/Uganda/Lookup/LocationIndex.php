<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;
use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class LocationIndex
{
    /** @var array<int, District> */
    private array $districts;

    /** @var array<string, array<int, District|County|SubCounty|Parish|Village>> */
    private array $byId = [];

    /** @var array<string, array<string, array<int, int>>> */
    private array $byName = [];

    /** @var array<string, bool> */
    private array $indexedIds = [];

    /** @var array<string, bool> */
    private array $indexedNames = [];

    /** @var array<string, array<int, string>> */
    private array $sortedNameKeys = [];

    /** @param array<int, District> $districts */
    public function __construct(array $districts)
    {
        // Retain the existing identity-preserving object tree.
        // Do not allocate five complete secondary indexes until queried.
        $this->districts = $districts;
    }

    public function byId(string $level, int $id): ?object
    {
        if (!isset($this->indexedIds[$level])) {
            $this->assertLevel($level);
            $this->indexIds($level);
        }

        return $this->byId[$level][$id] ?? null;
    }

    /** @return array<int, object> */
    public function byName(string $level, string $name, ?int $parentId = null): array
    {
        if (!isset($this->indexedNames[$level])) {
            $this->assertLevel($level);
            $this->indexNames($level);
        }

        $result = [];
        foreach ($this->byName[$level][self::nameKey($name)] ?? [] as $id) {
            $unit = $this->byId[$level][$id];
            if ($parentId === null || self::parentId($unit) === $parentId) {
                $result[] = $unit;
            }
        }

        return $result;
    }

    /**
     * Return all matching records using a lazily sorted name index.
     * First prefix lookup: O(U log U), then O(log U + M) over name keys
     * and matching identities for U names and M prefix candidates.
     *
     * @return array<int, object>
     */
    public function byPrefix(string $level, string $prefix, ?int $parentId = null, ?int $limit = null): array
    {
        $this->assertLevel($level);
        $prefix = self::nameKey($prefix);
        if ($prefix === '') {
            throw new InvalidArgumentException('A nonempty location name prefix is required');
        }
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('Prefix result limit must be positive');
        }

        $this->indexNames($level);
        if (!isset($this->sortedNameKeys[$level])) {
            // Numeric place names become integer keys in PHP arrays.
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

        $results = [];
        for ($i = $low, $count = count($names); $i < $count; $i++) {
            if (!str_starts_with($names[$i], $prefix)) {
                break;
            }
            foreach ($this->byName[$level][$names[$i]] as $id) {
                $unit = $this->byId[$level][$id];
                if ($parentId !== null && self::parentId($unit) !== $parentId) {
                    continue;
                }
                $results[] = $unit;
                if ($limit !== null && count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }

    /**
     * Directly reuse the parent object's canonical child collection.
     * Cost: parent ID lookup O(1) once indexed plus O(k) output records.
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

    /** @return array<int, object> */
    public function ancestorsOf(string $level, int $id): array
    {
        $unit = $this->byId($level, $id);
        if ($unit === null) {
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
            $parentId = self::parentId($unit);
            if ($parentId === null) {
                throw new InvalidArgumentException('Missing administrative parent for ' . $level . ' ' . $id);
            }
            $level = $parentLevels[$level];
            if (!isset($this->indexedIds[$level])) {
                $this->indexIds($level);
            }
            $parent = $this->byId[$level][$parentId] ?? null;
            if ($parent === null) {
                throw new InvalidArgumentException('Unknown administrative parent for ' . $level . ' ' . $parentId);
            }
            $ancestors[] = $parent;
            $unit = $parent;
            $id = $parentId;
        }

        return array_reverse($ancestors);
    }

    private function indexIds(string $level): void
    {
        if (isset($this->indexedIds[$level])) {
            return;
        }

        foreach ($this->unitsAtLevel($level) as $unit) {
            $id = $unit->id();
            if (isset($this->byId[$level][$id])) {
                throw new InvalidArgumentException("Duplicate $level identifier $id");
            }
            $this->byId[$level][$id] = $unit;
        }
        $this->indexedIds[$level] = true;
    }

    private function indexNames(string $level): void
    {
        if (isset($this->indexedNames[$level])) {
            return;
        }

        if (!isset($this->indexedIds[$level])) {
            // A first name query builds ID and name indexes in one traversal.
            foreach ($this->unitsAtLevel($level) as $unit) {
                $id = $unit->id();
                if (isset($this->byId[$level][$id])) {
                    throw new InvalidArgumentException("Duplicate $level identifier $id");
                }
                $this->byId[$level][$id] = $unit;
                $this->byName[$level][self::nameKey($unit->name())][] = $id;
            }
            $this->indexedIds[$level] = true;
        } else {
            foreach ($this->byId[$level] ?? [] as $id => $unit) {
                $this->byName[$level][self::nameKey($unit->name())][] = $id;
            }
        }

        $this->indexedNames[$level] = true;
    }

    private static function parentId(object $unit): ?int
    {
        if ($unit instanceof County) {
            return $unit->districtId();
        }
        if ($unit instanceof SubCounty) {
            return $unit->countyId();
        }
        if ($unit instanceof Parish) {
            return $unit->subCountyId();
        }
        if ($unit instanceof Village) {
            return $unit->parishId();
        }
        return null;
    }

    private static function nameKey(string $name): string
    {
        // Preserve v2's ASCII case-folding contract for exact and prefix
        // lookups. Locale-dependent normalizers must not silently alter IDs.
        return strtolower(trim($name));
    }

    /**
     * Iterate only the level requested. Do not build a flattened array of
     * tens of thousands of villages as an intermediate allocation.
     *
     * @return \Generator<int, District|County|SubCounty|Parish|Village>
     */
    private function unitsAtLevel(string $level): \Generator
    {
        foreach ($this->districts as $district) {
            if ($level === 'district') {
                yield $district;
                continue;
            }
            foreach ($district->counties() as $county) {
                if ($level === 'county') {
                    yield $county;
                    continue;
                }
                foreach ($county->subCounties() as $subcounty) {
                    if ($level === 'subcounty') {
                        yield $subcounty;
                        continue;
                    }
                    foreach ($subcounty->parishes() as $parish) {
                        if ($level === 'parish') {
                            yield $parish;
                            continue;
                        }
                        foreach ($parish->villages() as $village) {
                            yield $village;
                        }
                    }
                }
            }
        }
    }

    private function assertLevel(string $level): void
    {
        if (!in_array($level, ['district', 'county', 'subcounty', 'parish', 'village'], true)) {
            throw new InvalidArgumentException('Unsupported administrative level: ' . $level);
        }
    }
}
