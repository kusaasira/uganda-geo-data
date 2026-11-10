<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;
use Uganda\District;

final class LocationIndex
{
    /** @var array<string, array<int, object>> */
    private array $byId = [];

    /** @var array<string, array<string, array<int, array{unit: object, parent_id: int|null}>>> */
    private array $byName = [];

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
        $this->byName[$level][strtolower(trim($name))][] = ['unit' => $unit, 'parent_id' => $parentId];
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
        foreach ($this->byName[$level][strtolower(trim($name))] ?? [] as $candidate) {
            if ($parentId === null || $candidate['parent_id'] === $parentId) {
                $result[] = $candidate['unit'];
            }
        }

        return $result;
    }

    private function assertLevel(string $level): void
    {
        if (!in_array($level, ['district', 'county', 'subcounty', 'parish', 'village'], true)) {
            throw new InvalidArgumentException('Unsupported administrative level: ' . $level);
        }
    }
}
