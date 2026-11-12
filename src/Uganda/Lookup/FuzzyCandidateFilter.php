<?php

declare(strict_types=1);

namespace Uganda\Lookup;

/**
 * Necessary (not sufficient) condition for bounded OSA fuzzy matches.
 *
 * Each insertion/deletion changes a grapheme-bag histogram by one count,
 * substitution by at most two and adjacent transposition by zero. Therefore,
 * a name within k edits cannot have histogram L1 difference greater than 2k.
 *
 * 41 exact ASCII buckets plus 32 hashed Unicode grapheme buckets are used.
 * Hash collisions only underestimate the difference, so they cannot cause
 * false negatives. The signatures are built only for opt-in fuzzy queries.
 */
final class FuzzyCandidateFilter
{
    private const ASCII_BUCKETS = 41;

    private const HASH_BUCKETS = 32;

    private const SIGNATURE_BYTES = self::ASCII_BUCKETS + self::HASH_BUCKETS;

    public static function signature(string $name): string
    {
        $signature = str_repeat("\0", self::SIGNATURE_BYTES);
        // Typical Ugandan locality names are ASCII. Avoid UTF-8 segmentation
        // and ICU allocation for that hot indexing path.
        if (preg_match('/[^\x00-\x7F]/', $name) === 0) {
            for ($i = 0, $length = strlen($name); $i < $length; $i++) {
                self::increment($signature, self::asciiBucket(ord($name[$i])));
            }

            return $signature;
        }

        foreach (UnicodeNameNormalizer::graphemes($name) as $cluster) {
            $position = strlen($cluster) === 1 && ord($cluster) < 128
                ? self::asciiBucket(ord($cluster))
                : self::ASCII_BUCKETS + (crc32($cluster) & (self::HASH_BUCKETS - 1));
            self::increment($signature, $position);
        }

        return $signature;
    }

    public static function mayMatch(?string $query, ?string $candidate, int $maxDistance): bool
    {
        if ($query === null || $candidate === null) {
            return true;
        }

        $difference = 0;
        $allowed = $maxDistance * 2;
        for ($i = 0; $i < self::SIGNATURE_BYTES; $i++) {
            $difference += abs(ord($query[$i]) - ord($candidate[$i]));
            if ($difference > $allowed) {
                return false;
            }
        }

        return true;
    }

    private static function increment(string &$signature, int $position): void
    {
        $count = ord($signature[$position]);
        // Saturation only underestimates multiset differences, remaining
        // mathematically safe for names with many repeated graphemes.
        if ($count < 255) {
            $signature[$position] = chr($count + 1);
        }
    }

    private static function asciiBucket(int $byte): int
    {
        return match (true) {
            $byte >= 97 && $byte <= 122 => $byte - 97,
            $byte >= 48 && $byte <= 57 => 26 + $byte - 48,
            $byte === 32 => 36,
            $byte === 45 => 37,
            $byte === 39 => 38,
            $byte === 46 => 39,
            $byte === 47 => 40,
            default => self::ASCII_BUCKETS + ($byte & (self::HASH_BUCKETS - 1)),
        };
    }
}
