<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Lookup\FuzzyCandidateFilter;
use Uganda\Lookup\UnicodeEditDistance;

final class FuzzyUnicodeHistogramPropertyTest extends TestCase
{
    #[Test]
    public function hashed_grapheme_histograms_never_prune_valid_unicode_osa_matches(): void
    {
        $symbols = ['a', 'b', 'é', 'ø', 'å', 'क', '🇺🇬', '🇰🇪', '👩‍💻', '가', '-', ' '];
        $names = [''];
        $state = 19733;
        for ($i = 0; $i < 175; $i++) {
            $length = 1 + ($i % 5);
            $word = '';
            for ($j = 0; $j < $length; $j++) {
                $state = (int) (($state * 48271) % 2147483647);
                $word .= $symbols[$state % count($symbols)];
            }
            $names[] = $word;
        }

        $signatures = [];
        foreach ($names as $name) {
            $signatures[$name] = FuzzyCandidateFilter::signature($name);
            self::assertSame(73, strlen($signatures[$name]));
        }

        foreach ($names as $query) {
            foreach ($names as $candidate) {
                for ($distance = 0; $distance <= 3; $distance++) {
                    $actual = UnicodeEditDistance::within($query, $candidate, $distance);
                    if ($actual === null) {
                        continue;
                    }
                    self::assertTrue(
                        FuzzyCandidateFilter::mayMatch(
                            $signatures[$query],
                            $signatures[$candidate],
                            $distance
                        ),
                        "Grapheme histogram falsely removed $candidate for query $query at $distance edits"
                    );
                }
            }
        }
    }

    #[Test]
    public function very_long_grapheme_runs_saturate_without_wrapping(): void
    {
        $query = FuzzyCandidateFilter::signature(str_repeat('é', 300));
        $candidate = FuzzyCandidateFilter::signature(str_repeat('é', 299));
        self::assertTrue(FuzzyCandidateFilter::mayMatch($query, $candidate, 1));
        self::assertSame(73, strlen($query));
    }
}
