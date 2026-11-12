<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;
use Uganda\County;
use Uganda\District;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Village;

final class LocationPresenter
{
    /** @return array{id: int, name: string, level: string, parent_id: int|null} */
    public static function toArray(object $location): array
    {
        if ($location instanceof District) {
            $level = 'district';
            $parentId = null;
        } elseif ($location instanceof County) {
            $level = 'county';
            $parentId = $location->districtId();
        } elseif ($location instanceof SubCounty) {
            $level = 'subcounty';
            $parentId = $location->countyId();
        } elseif ($location instanceof Parish) {
            $level = 'parish';
            $parentId = $location->subCountyId();
        } elseif ($location instanceof Village) {
            $level = 'village';
            $parentId = $location->parishId();
        } else {
            throw new InvalidArgumentException('Unsupported administrative location model');
        }

        return [
            'id' => $location->id(),
            'name' => $location->name(),
            'level' => $level,
            'parent_id' => $parentId,
        ];
    }
}
