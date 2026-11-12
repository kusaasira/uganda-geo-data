<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Uganda;

final class LosslessHierarchyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function retainsEveryBundledRowAtEveryHierarchyLevel(): void
    {
        $uganda = new Uganda();
        $root = dirname(__DIR__, 2) . '/src/Uganda/Data/';
        $levels = [
            'districts.json' => $uganda->districts(),
            'counties.json' => $uganda->counties(),
            'sub_counties.json' => $uganda->subCounties(),
            'parishes.json' => $uganda->parishes(),
            'villages.json' => $uganda->villages(),
        ];
        foreach ($levels as $file => $units) {
            $rows = json_decode((string) file_get_contents($root . $file), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(count($rows), $units, $file . ' lost source records');
            $ids = array_map(static function ($unit): int {
                return $unit->id();
            }, $units);
            $this->assertCount(count($ids), array_unique($ids), $file . ' has duplicate IDs');
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function preservesSiblingRecordsEvenWhenTheirNamesMatch(): void
    {
        $uganda = new Uganda();
        $root = dirname(__DIR__, 2) . '/src/Uganda/Data/';
        foreach ([
            'parishes.json' => ['parent' => 'subcounty', 'units' => $uganda->parishes(), 'method' => 'subCountyId'],
            'villages.json' => ['parent' => 'parish', 'units' => $uganda->villages(), 'method' => 'parishId'],
        ] as $file => $definition) {
            $rows = json_decode((string) file_get_contents($root . $file), true, 512, JSON_THROW_ON_ERROR);
            $seen = [];
            $collision = null;
            foreach ($rows as $row) {
                $key = $row[$definition['parent']] . ':' . strtolower($row['name']);
                if (isset($seen[$key])) {
                    $collision = $row;
                    break;
                }
                $seen[$key] = true;
            }
            $this->assertNotNull($collision, 'Missing expected collision fixture');
            $ids = [];
            foreach ($definition['units'] as $unit) {
                if ($unit->{$definition['method']}() === (int) $collision[$definition['parent']] &&
                    strtolower($unit->name()) === strtolower($collision['name'])) {
                    $ids[] = $unit->id();
                }
            }
            $this->assertGreaterThanOrEqual(2, count(array_unique($ids)));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function supportsIdBasedCollectionsAndExistingUniqueDistrictLookup(): void
    {
        $uganda = new Uganda();
        $district = $uganda->district('Bukomansimbi');
        $this->assertSame($district, $uganda->districts()[$district->id()]);
    }
}
