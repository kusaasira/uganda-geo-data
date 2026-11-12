<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\HierarchyOracle;
use Tests\Support\SeededHierarchyFixture;

require_once dirname(__DIR__) . '/support/SeededHierarchyFixture.php';
require_once dirname(__DIR__) . '/support/HierarchyOracle.php';

final class SeededHierarchyFixtureTest extends TestCase
{
    #[Test]
    public function the_same_seed_recreates_identical_record_and_parent_sequences(): void
    {
        foreach ([1, 17, 71, 20261112, 2147483646] as $seed) {
            $first = HierarchyOracle::flatten((new SeededHierarchyFixture($seed))->generate());
            $second = HierarchyOracle::flatten((new SeededHierarchyFixture($seed))->generate());
            foreach ($first as $level => $models) {
                $records = static fn (array $units): array => array_map(
                    static fn (object $unit): array => [$unit->id(), $unit->name(), HierarchyOracle::parentId($unit)],
                    $units
                );
                self::assertSame($records($models), $records($second[$level]), "seed=$seed level=$level");
                self::assertCount(count($models), array_unique(HierarchyOracle::ids($models)), "seed=$seed level=$level");
            }
        }
    }

    #[Test]
    public function invalid_random_seeds_and_ranges_are_rejected(): void
    {
        foreach ([0, -1, 2147483647] as $seed) {
            try {
                new SeededHierarchyFixture($seed);
                self::fail('Seed must reject values outside the positive 31-bit range');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('seed', $exception->getMessage());
            }
        }

        $stream = new SeededHierarchyFixture(42);
        $this->expectException(InvalidArgumentException::class);
        $stream->nextInt(9, 2);
    }
}
