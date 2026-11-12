<?php

declare(strict_types=1);

namespace Tests\Support;

use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class HierarchyOracle
{
    /**
     * @param array<int, District> $districts
     * @return array<string, array<int, District|County|SubCounty|Parish|Village>>
     */
    public static function flatten(array $districts): array
    {
        $levels = [
            'district' => [], 'county' => [], 'subcounty' => [],
            'parish' => [], 'village' => [],
        ];
        foreach ($districts as $district) {
            $levels['district'][] = $district;
            foreach ($district->counties() as $county) {
                $levels['county'][] = $county;
                foreach ($county->subCounties() as $subcounty) {
                    $levels['subcounty'][] = $subcounty;
                    foreach ($subcounty->parishes() as $parish) {
                        $levels['parish'][] = $parish;
                        foreach ($parish->villages() as $village) {
                            $levels['village'][] = $village;
                        }
                    }
                }
            }
        }

        return $levels;
    }

    public static function parentId(object $unit): ?int
    {
        if ($unit instanceof Village) {
            return $unit->parishId();
        }
        if ($unit instanceof Parish) {
            return $unit->subCountyId();
        }
        if ($unit instanceof SubCounty) {
            return $unit->countyId();
        }
        if ($unit instanceof County) {
            return $unit->districtId();
        }

        return null;
    }

    /**
     * @param array<int, District|County|SubCounty|Parish|Village> $source
     * @return array<int, District|County|SubCounty|Parish|Village>
     */
    public static function exact(array $source, string $name, ?int $parentId = null): array
    {
        $normalized = strtolower(trim($name));

        return array_values(array_filter($source, static function (object $unit) use ($normalized, $parentId): bool {
            return strtolower(trim($unit->name())) === $normalized
                && ($parentId === null || self::parentId($unit) === $parentId);
        }));
    }

    /**
     * @param array<int, District|County|SubCounty|Parish|Village> $source
     * @return array<int, District|County|SubCounty|Parish|Village>
     */
    public static function prefix(array $source, string $prefix, ?int $parentId = null, ?int $limit = null): array
    {
        $needle = strtolower(trim($prefix));
        $candidates = [];
        foreach ($source as $position => $unit) {
            if (str_starts_with(strtolower(trim($unit->name())), $needle)
                && ($parentId === null || self::parentId($unit) === $parentId)) {
                $candidates[] = ['unit' => $unit, 'position' => $position];
            }
        }
        usort($candidates, static function (array $left, array $right): int {
            $compare = strcmp(
                strtolower(trim($left['unit']->name())),
                strtolower(trim($right['unit']->name()))
            );
            return $compare !== 0 ? $compare : $left['position'] <=> $right['position'];
        });

        $result = array_map(static fn (array $entry): object => $entry['unit'], $candidates);

        return $limit === null ? $result : array_slice($result, 0, $limit);
    }

    /** @param array<int, object> $models @return array<int, int> */
    public static function ids(array $models): array
    {
        return array_map(static fn (object $unit): int => $unit->id(), $models);
    }
}
