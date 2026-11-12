<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;
use Uganda\Parish;
use Uganda\SubCounty;
use Uganda\Uganda;
use Uganda\Village;

final class HierarchyStreamingTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itYieldsOriginalModelsWithoutConstructingSecondaryIndexes(): void
    {
        $village = new Village(5, 'Village', 4);
        $parish = new Parish(4, 'Parish', 3, [5 => $village]);
        $subcounty = new SubCounty(3, 'Subcounty', 2, [4 => $parish]);
        $county = new County(2, 'County', 1, [3 => $subcounty]);
        $district = new District(1, 'District', [2 => $county]);
        $index = new LocationIndex([1 => $district]);

        self::assertSame([$district], iterator_to_array($index->iterate('district')));
        self::assertSame([$county], iterator_to_array($index->iterate('county')));
        self::assertSame([$subcounty], iterator_to_array($index->iterate('subcounty')));
        self::assertSame([$parish], iterator_to_array($index->iterate('parish')));
        self::assertSame([$village], iterator_to_array($index->iterate('village')));
        $byId = (new ReflectionProperty(LocationIndex::class, 'byId'))->getValue($index);
        self::assertSame([], $byId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itMatchesEveryBundledSourceRecordInOrder(): void
    {
        $geo = new Uganda();
        $levels = [
            'district' => 'districts',
            'county' => 'counties',
            'subcounty' => 'subCounties',
            'parish' => 'parishes',
            'village' => 'villages',
        ];

        foreach ($levels as $level => $collection) {
            $expected = array_map(static fn ($record): int => $record->id(), $geo->$collection());
            $actual = [];
            foreach ($geo->iterate($level) as $record) {
                $actual[] = $record->id();
            }
            self::assertSame(array_values($expected), $actual, $level);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function aGeneratorFailsForAnUnsupportedLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new Uganda())->iterate('province'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itSupportsEarlyTerminationWithoutMaterializingAllVillages(): void
    {
        $geo = new Uganda();
        $first = null;
        foreach ($geo->iterate('village') as $village) {
            $first = $village;
            break;
        }
        self::assertInstanceOf(Village::class, $first);
        self::assertSame($first, $geo->findById('village', $first->id()));
    }
}
