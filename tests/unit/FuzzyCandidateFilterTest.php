<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Lookup\FuzzyCandidateFilter;
use Uganda\Lookup\UnicodeEditDistance;

final class FuzzyCandidateFilterTest extends TestCase
{
    #[Test]
    public function filter_never_rejects_any_known_osa_match_under_its_edit_limit(): void
    {
        $names = ['', 'a', 'b', 'ab', 'ba', 'aa', 'bb', 'aba', 'abb', 'baa', 'bab',
            'aab', 'bba', 'abba', 'baab', 'abab', 'baba', 'aabb', 'aaa', 'bbb',
            'abc', 'acb', 'bac', 'cab', 'bca', 'cba', 'a c', "a'b", 'a-b'];

        foreach ($names as $query) {
            foreach ($names as $name) {
                $first = FuzzyCandidateFilter::signature($query);
                $second = FuzzyCandidateFilter::signature($name);
                for ($limit = 0; $limit <= 3; $limit++) {
                    if (UnicodeEditDistance::within($query, $name, $limit) !== null) {
                        self::assertTrue(
                            FuzzyCandidateFilter::mayMatch($first, $second, $limit),
                            "Filtered valid OSA match: query=$query name=$name limit=$limit"
                        );
                    }
                }
            }
        }
    }

    #[Test]
    public function candidate_signatures_are_fixed_size_and_prune_unrelated_ascii_words(): void
    {
        $query = FuzzyCandidateFilter::signature('kampala');
        $unrelated = FuzzyCandidateFilter::signature('zimbabwe');
        self::assertNotNull($query);
        self::assertNotNull($unrelated);
        self::assertSame(73, strlen($query));
        self::assertFalse(FuzzyCandidateFilter::mayMatch($query, $unrelated, 2));
        self::assertTrue(FuzzyCandidateFilter::mayMatch(
            $query,
            FuzzyCandidateFilter::signature('kapmala'),
            1
        ));
    }

    #[Test]
    public function unicode_grapheme_histograms_are_safe_and_saturated_counts_never_reject_true_matches(): void
    {
        self::assertSame(73, strlen(FuzzyCandidateFilter::signature('École')));
        self::assertSame(73, strlen(FuzzyCandidateFilter::signature('東京')));
        self::assertTrue(FuzzyCandidateFilter::mayMatch(null, FuzzyCandidateFilter::signature('ecole'), 0));
        self::assertTrue(FuzzyCandidateFilter::mayMatch(
            FuzzyCandidateFilter::signature('école'),
            FuzzyCandidateFilter::signature('ecole'),
            1
        ));
        self::assertTrue(FuzzyCandidateFilter::mayMatch(
            FuzzyCandidateFilter::signature('🇺🇬🇰🇪'),
            FuzzyCandidateFilter::signature('🇰🇪🇺🇬'),
            1
        ));
        self::assertTrue(FuzzyCandidateFilter::mayMatch(
            FuzzyCandidateFilter::signature(str_repeat('a', 300)),
            FuzzyCandidateFilter::signature(str_repeat('a', 299)),
            1
        ));
    }
}
