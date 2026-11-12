<?php

declare(strict_types=1);

namespace Tests\Unit;

use Normalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Uganda\County;
use Uganda\District;
use Uganda\Lookup\LocationIndex;
use Uganda\Lookup\UnicodeNameNormalizer;

final class UnicodeAdversarialBoundariesTest extends TestCase
{
    #[Test]
    public function unicode_confusables_are_not_silently_accepted_as_identical_places(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('Unicode normalization requires intl');
        }

        // The second spelling contains a Cyrillic a, not a Latin a.
        $spoof = "Kamp\u{0430}la";
        self::assertNotSame(
            UnicodeNameNormalizer::normalize('Kampala'),
            UnicodeNameNormalizer::normalize($spoof)
        );

        $index = new LocationIndex([
            1 => new District(1, 'District', [
                11 => new County(11, 'Kampala', 1),
                12 => new County(12, $spoof, 1),
            ]),
        ]);
        self::assertSame([11], array_map(
            static fn (County $county): int => $county->id(),
            $index->byUnicodeName('county', 'Kampala')
        ));
        self::assertSame([12], array_map(
            static fn (County $county): int => $county->id(),
            $index->byUnicodeName('county', $spoof)
        ));
    }

    #[Test]
    public function huge_or_invalid_queries_fail_before_allocating_dictionary_indexes(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('Unicode normalization requires intl');
        }
        $index = new LocationIndex([
            1 => new District(1, 'District', [2 => new County(2, 'Central', 1)]),
        ]);

        $invalid = [
            static fn () => $index->byUnicodeName('county', str_repeat('a', 4097)),
            static fn () => $index->byUnicodePrefix('county', str_repeat('🇺🇬', 1100)),
            static fn () => $index->fuzzy('county', str_repeat('e', 4097)),
            static fn () => $index->fuzzy('county', 'Cen' . "\xF4\x28\x8C\x28"),
            static fn () => $index->byUnicodeName('county', str_repeat('a', 129)),
        ];
        foreach ($invalid as $case => $query) {
            try {
                $query();
                self::fail("Accepted invalid query $case");
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }

        $unicode = (new ReflectionProperty(LocationIndex::class, 'unicodeSearch'))->getValue($index);
        self::assertNotNull($unicode);
        self::assertSame(
            [],
            (new ReflectionProperty($unicode, 'namesByIndex'))->getValue($unicode),
            'Invalid queries should not build any Unicode dictionary'
        );
    }

    #[Test]
    public function canonically_equivalent_names_preserve_separate_source_identifiers(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('Unicode normalization requires intl');
        }

        $index = new LocationIndex([
            1 => new District(1, 'District', [
                9 => new County(9, 'École', 1),
                7 => new County(7, "E\u{0301}cole", 1),
                3 => new County(3, 'Ecole', 1),
            ]),
        ]);

        self::assertSame([9, 7], array_map(
            static fn (County $unit): int => $unit->id(),
            $index->byUnicodeName('county', 'école')
        ));
        self::assertSame([9, 7, 3], array_map(
            static fn (County $unit): int => $unit->id(),
            $index->byUnicodeName('county', 'ecole', null, true)
        ));
        self::assertSame([9, 7, 3], array_map(
            static fn (County $unit): int => $unit->id(),
            $index->byUnicodePrefix('county', 'eco', null, null, true)
        ));
    }
}
