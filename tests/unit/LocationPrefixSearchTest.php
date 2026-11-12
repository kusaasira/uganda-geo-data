<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;

final class LocationPrefixSearchTest extends TestCase
{
    private function index(): LocationIndex
    {
        return new LocationIndex([
            1 => new District(1, 'First', [
                8 => new County(8, 'Central', 1),
                9 => new County(9, 'Cen', 1),
                10 => new County(10, 'North', 1),
                11 => new County(11, 'Centenary', 1),
            ]),
            2 => new District(2, 'Second', [
                12 => new County(12, 'Central', 2),
            ]),
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itReturnsEveryMatchingIdentityInDeterministicNameOrder(): void
    {
        $matches = $this->index()->byPrefix('county', ' cen ');
        self::assertSame([9, 11, 8, 12], array_map(static fn ($x): int => $x->id(), $matches));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itFiltersByParentBeforeApplyingTheResultLimit(): void
    {
        $index = $this->index();
        $matches = $index->byPrefix('county', 'CEN', 1, 2);

        self::assertSame([9, 11], array_map(static fn ($x): int => $x->id(), $matches));
        self::assertSame([12], array_map(static fn ($x): int => $x->id(), $index->byPrefix('county', 'cen', 2)));
        self::assertSame([], $index->byPrefix('county', 'cen', 999));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itKeepsExistingExactNameSearchUnchanged(): void
    {
        $index = $this->index();
        $index->byPrefix('county', 'ce');

        self::assertSame([8, 12], array_map(static fn ($x): int => $x->id(), $index->byName('county', 'Central')));
        self::assertSame([8], array_map(static fn ($x): int => $x->id(), $index->byName('county', 'Central', 1)));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsAnEmptyPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->index()->byPrefix('county', '   ');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsNonpositiveLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->index()->byPrefix('county', 'ce', null, 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function itRejectsUnsupportedLevels(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->index()->byPrefix('province', 'cen');
    }
}
