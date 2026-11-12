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

final class UnicodeSearchIntegrationTest extends TestCase
{
    private function locations(): LocationIndex
    {
        $first = [
            21 => new County(21, 'École', 1),
            22 => new County(22, "E\u{0301}COLE", 1),
            23 => new County(23, 'ECOLE', 1),
            24 => new County(24, 'Straße', 1),
            25 => new County(25, 'STRASSE', 1),
            26 => new County(26, 'ＭＵＫＯＮＯ', 1),
            27 => new County(27, 'Mukono', 1),
            28 => new County(28, 'Kampala', 1),
            29 => new County(29, 'Kampalaa', 1),
            30 => new County(30, 'Kampala', 1),
            31 => new County(31, 'Café', 1),
            32 => new County(32, 'Cafe', 1),
        ];
        $second = [
            33 => new County(33, 'Kampala', 2),
            34 => new County(34, 'KAPMALA', 2),
            35 => new County(35, 'Café', 2),
        ];

        return new LocationIndex([
            1 => new District(1, 'One', $first),
            2 => new District(2, 'Two', $second),
        ]);
    }

    #[Test]
    public function unicode_search_uses_true_unicode_casefold_and_canonical_equivalence(): void
    {
        $this->requireIntl();
        $locations = $this->locations();

        self::assertSame([21, 22], $this->ids($locations->byUnicodeName('county', 'école')));
        self::assertSame([21, 22, 23], $this->ids($locations->byUnicodeName('county', 'ECOLE', null, true)));
        self::assertSame([24, 25], $this->ids($locations->byUnicodeName('county', 'STRASSE')));
        self::assertSame([26, 27], $this->ids($locations->byUnicodeName('county', 'mukono')));
        self::assertSame([31, 35], $this->ids($locations->byUnicodeName('county', 'CAFÉ')));
        self::assertSame([31, 32, 35], $this->ids($locations->byUnicodeName('county', 'cafe', null, true)));

        // Existing ASCII exact search semantics and indexes are untouched.
        self::assertSame([23], $this->ids($locations->byName('county', 'ECOLE')));
        self::assertSame([21], $this->ids($locations->byName('county', 'École')));
    }

    #[Test]
    public function prefix_queries_are_stable_before_and_after_index_warmup(): void
    {
        $this->requireIntl();
        $index = $this->locations();

        self::assertSame([21, 22, 23], $this->ids($index->byUnicodePrefix('county', 'e', 1, null, true)));
        self::assertSame([26, 27], $this->ids($index->byUnicodePrefix('county', 'ＭＵ', 1)));
        self::assertSame([28, 30], $this->ids($index->byUnicodePrefix('county', 'kampala', 1, 2)));
        self::assertSame([], $this->ids($index->byUnicodePrefix('county', 'unknown', 999999)));

        $index->byUnicodePrefix('county', 'cafe', null, null, true);
        self::assertSame([31, 32], $this->ids($index->byUnicodePrefix('county', 'cafe', 1, 2, true)));
        self::assertSame([31, 32, 35], $this->ids($index->byUnicodePrefix('county', 'cafe', null, null, true)));
    }

    #[Test]
    public function fuzzy_scoring_prioritizes_exact_names_and_detects_transposition_typos(): void
    {
        $this->requireIntl();
        $index = $this->locations();

        $scores = $index->fuzzy('county', 'kapmala', 1, 5, 1);
        self::assertSame([28, 30], $this->ids(array_column($scores, 'unit')));
        self::assertSame([1, 1], array_column($scores, 'distance'));

        $scores = $index->fuzzy('county', 'kampala', null, 10, 1);
        self::assertSame([28, 30, 33, 29, 34], $this->ids(array_column($scores, 'unit')));
        self::assertSame([0, 0, 0, 1, 1], array_column($scores, 'distance'));

        self::assertSame([28, 30], $this->ids(array_column(
            $index->fuzzy('county', 'kampala', 1, 10, 0),
            'unit'
        )));
        self::assertSame([28], $this->ids(array_column(
            $index->fuzzy('county', 'kampala', 1, 1, 2),
            'unit'
        )));
    }

    #[Test]
    public function unicode_index_cache_is_bounded_and_has_no_legacy_index_side_effects(): void
    {
        $this->requireIntl();
        $index = $this->locations();
        $index->byUnicodeName('county', 'école');
        $index->byUnicodePrefix('county', 'caf', null, 2, true);
        $index->fuzzy('district', 'on', null, 1, 2);
        $cache = (new ReflectionProperty(LocationIndex::class, 'unicodeSearch'))->getValue($index);
        self::assertNotNull($cache);

        $names = (new ReflectionProperty($cache, 'namesByIndex'))->getValue($cache);
        self::assertLessThanOrEqual(2, count($names));

        $legacyNames = (new ReflectionProperty(LocationIndex::class, 'byName'))->getValue($index);
        self::assertSame([], $legacyNames);

        self::assertSame([21, 22], $this->ids($index->byUnicodeName('county', 'école')));
    }

    #[Test]
    public function district_parent_scopes_reject_results_without_exceptions(): void
    {
        $this->requireIntl();
        $index = $this->locations();
        self::assertSame([], $index->byUnicodeName('district', 'One', 1));
        self::assertSame([], $index->byUnicodePrefix('district', 'on', 1));
        self::assertSame([], $index->fuzzy('district', 'on', 1));
    }

    /** @param array<int, object> $units @return array<int, int> */
    private function ids(array $units): array
    {
        return array_map(static fn (object $unit): int => $unit->id(), $units);
    }

    private function requireIntl(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('Unicode search integration requires ext-intl');
        }
    }
}
