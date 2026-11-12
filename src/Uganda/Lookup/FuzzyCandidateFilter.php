<?php

declare(strict_types=1);

namespace Uganda\Lookup;

/**
 * A necessary (not sufficient) condition for an edit match.
 *
 * One insertion/deletion changes a character histogram by one count, one
 * substitution by at most two and one adjacent transposition by zero.
 * Consequently, any name within k OSA edits has histogram L1 distance <=2k.
 *
 * The packed ASCII signature is optional. For non-ASCII or unusual scripts,
 * return null and let the exact Unicode edit-distance verifier decide.
 * This never discards otherwise-valid Unicode matches.
 */
final class FuzzyCandidateFilter
{
    private const SIGNATURE_BYTES = 41;

    public static function signature(string $name): ?string
    {
        $signature = str_repeat("\0", self::SIGNATURE_BYTES);
        for ($i = 0, $length = strlen($name); $i < $length; $i++) {
            $byte = ord($name[$i]);
            $position = match (true) {
                $byte >= 97 && $byte <= 122 => $byte - 97,
                $byte >= 48 && $byte <= 57 => 26 + $byte - 48,
                $byte === 32 => 36,
                $byte === 45 => 37,
                $byte === 39 => 38,
                $byte === 46 => 39,
                $byte === 47 => 40,
                default => -1,
            };
            if ($position < 0) {
                return null;
            }
            $count = ord($signature[$position]);
            // Saturate instead of wrapping. Underestimating differences can
            // cost extra work but must never produce a false rejection.
            if ($count < 255) {
                $signature[$position] = chr($count + 1);
            }
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
}
