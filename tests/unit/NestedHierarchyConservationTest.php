<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use Uganda\Uganda;

final class NestedHierarchyConservationTest extends TestCase
{
    /** @test */
    public function retainsSameNamedSiblingsInNestedParishSerialization(): void
    {
        $uganda = new Uganda();
        $root = dirname(__DIR__, 2) . '/src/Uganda/Data/';
        $rows = json_decode((string) file_get_contents($root . 'parishes.json'), true, 512, JSON_THROW_ON_ERROR);
        $seen = [];
        $collision = null;
        foreach ($rows as $row) {
            $key = $row['subcounty'] . ':' . strtolower($row['name']);
            if (isset($seen[$key])) {
                $collision = $row;
                break;
            }
            $seen[$key] = true;
        }
        $this->assertNotNull($collision);
        foreach ($uganda->subCounties() as $unit) {
            if ($unit->id() !== (int) $collision['subcounty']) {
                continue;
            }
            $ids = [];
            foreach ($unit->parishes() as $parish) {
                if (strtolower($parish->name()) === strtolower($collision['name'])) {
                    $ids[] = $parish->id();
                }
            }
            $this->assertGreaterThanOrEqual(2, count(array_unique($ids)));
            $serialized = $unit->toArray()['parishes'];
            $this->assertCount(count($unit->parishes()), $serialized);
            foreach ($ids as $id) {
                $this->assertArrayHasKey($id, $serialized);
            }
            return;
        }
        $this->fail('Unable to locate the expected parent subcounty in the loaded hierarchy');
    }

    /** @test */
    public function retainsSameNamedVillagesInParishSerialization(): void
    {
        $uganda = new Uganda();
        $root = dirname(__DIR__, 2) . '/src/Uganda/Data/';
        $rows = json_decode((string) file_get_contents($root . 'villages.json'), true, 512, JSON_THROW_ON_ERROR);
        $seen = [];
        $collision = null;
        foreach ($rows as $row) {
            $key = $row['parish'] . ':' . strtolower($row['name']);
            if (isset($seen[$key])) {
                $collision = $row;
                break;
            }
            $seen[$key] = true;
        }
        $this->assertNotNull($collision);
        foreach ($uganda->parishes() as $parish) {
            if ($parish->id() !== (int) $collision['parish']) {
                continue;
            }
            $matches = [];
            foreach ($parish->villages() as $village) {
                if (strtolower($village->name()) === strtolower($collision['name'])) {
                    $matches[] = $village->id();
                }
            }
            $this->assertGreaterThanOrEqual(2, count(array_unique($matches)));
            $serialized = $parish->toArray()['villages'];
            $this->assertCount(count($parish->villages()), $serialized);
            foreach ($matches as $id) {
                $this->assertArrayHasKey($id, $serialized);
            }
            return;
        }
        $this->fail('Unable to locate the expected parent parish in the loaded hierarchy');
    }
}
