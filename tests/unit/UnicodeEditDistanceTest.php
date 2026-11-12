<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Lookup\UnicodeEditDistance;

final class UnicodeEditDistanceTest extends TestCase
{
    #[Test]
    public function basic_edits_transpositions_and_multibyte_graphemes_are_counted(): void
    {
        self::assertSame(0, UnicodeEditDistance::within('kampala', 'kampala', 0));
        self::assertSame(1, UnicodeEditDistance::within('kampala', 'kampalaa', 1));
        self::assertSame(1, UnicodeEditDistance::within('kampala', 'kampalaa', 1));
        self::assertSame(1, UnicodeEditDistance::within('kampala', 'kapmala', 1));
        self::assertSame(1, UnicodeEditDistance::within('kampala', 'kampal', 1));
        self::assertSame(1, UnicodeEditDistance::within('mukono', 'mukona', 1));
        self::assertNull(UnicodeEditDistance::within('mukono', 'xxxxxxxx', 2));
        self::assertSame(1, UnicodeEditDistance::within('🇺🇬🇰🇪', '🇰🇪🇺🇬', 1));
        self::assertSame(1, UnicodeEditDistance::within('新山', '山新', 1));
        self::assertSame(1, UnicodeEditDistance::within('नम', 'नमक', 1));
    }

    #[Test]
    public function bounded_distance_matches_an_unbanded_independent_reference(): void
    {
        $strings = ['', 'a', 'b', 'aa', 'ab', 'ba', 'aaa', 'aab', 'aba', 'abb', 'baa', 'bab', 'bba',
            'bbb', 'abba', 'baab', 'abab', 'baba', 'aabb', 'aaaa', 'bbbb',
            'é', 'éa', 'aé', 'å', '🇺🇬', '🇰🇪', '🇺🇬🇰🇪', '🇰🇪🇺🇬'];

        foreach ($strings as $left) {
            foreach ($strings as $right) {
                $expected = self::reference($left, $right);
                for ($max = 0; $max <= 3; $max++) {
                    self::assertSame(
                        $expected <= $max ? $expected : null,
                        UnicodeEditDistance::within($left, $right, $max),
                        "left=$left right=$right threshold=$max"
                    );
                }
            }
        }
    }

    #[Test]
    public function unsupported_edit_distance_is_rejected(): void
    {
        foreach ([-1, 4] as $maximum) {
            try {
                UnicodeEditDistance::within('abc', 'def', $maximum);
                self::fail('Out-of-range edit distance accepted');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('between 0 and 3', $e->getMessage());
            }
        }
    }

    private static function reference(string $left, string $right): int
    {
        $a = preg_split('/\X/u', $left, -1, PREG_SPLIT_NO_EMPTY);
        $b = preg_split('/\X/u', $right, -1, PREG_SPLIT_NO_EMPTY);
        // preg_split on a grapheme delimiter removes all characters: use
        // preg_match_all to obtain the complete reference sequence instead.
        preg_match_all('/\X/u', $left, $first);
        preg_match_all('/\X/u', $right, $second);
        $a = $first[0];
        $b = $second[0];

        $rows = [];
        $rows[0] = range(0, count($b));
        for ($i = 1; $i <= count($a); $i++) {
            $rows[$i] = [$i];
            for ($j = 1; $j <= count($b); $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $distance = min(
                    $rows[$i - 1][$j] + 1,
                    $rows[$i][$j - 1] + 1,
                    $rows[$i - 1][$j - 1] + $cost
                );
                if ($i > 1 && $j > 1
                    && $a[$i - 1] === $b[$j - 2]
                    && $a[$i - 2] === $b[$j - 1]) {
                    $distance = min($distance, $rows[$i - 2][$j - 2] + 1);
                }
                $rows[$i][$j] = $distance;
            }
        }

        return $rows[count($a)][count($b)];
    }
}
