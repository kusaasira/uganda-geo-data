<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;

/**
 * Bounded optimal-string-alignment edit distance on extended grapheme clusters.
 * Supports substitution, insertion, deletion and one adjacent transposition.
 *
 * O(min(lengths) * (2 * maxDistance + 1)) cell visits with a diagonal band,
 * and O(shorter length) auxiliary memory. Null means outside the threshold.
 */
final class UnicodeEditDistance
{
    public static function within(string $first, string $second, int $maxDistance): ?int
    {
        if ($maxDistance < 0 || $maxDistance > 3) {
            throw new InvalidArgumentException('Fuzzy search edit distance must be between 0 and 3');
        }
        if ($first === $second) {
            return 0;
        }

        $a = UnicodeNameNormalizer::graphemes($first);
        $b = UnicodeNameNormalizer::graphemes($second);
        if (count($a) > count($b)) {
            [$a, $b] = [$b, $a];
        }
        $n = count($a);
        $m = count($b);
        if ($m - $n > $maxDistance) {
            return null;
        }

        // Remove a common prefix and suffix before touching the DP matrix.
        while ($a !== [] && $b !== [] && $a[0] === $b[0]) {
            array_shift($a);
            array_shift($b);
        }
        while ($a !== [] && $b !== []
            && $a[count($a) - 1] === $b[count($b) - 1]) {
            array_pop($a);
            array_pop($b);
        }

        $n = count($a);
        $m = count($b);
        if ($n === 0) {
            return $m <= $maxDistance ? $m : null;
        }
        if ($m - $n > $maxDistance) {
            return null;
        }

        $infinity = $maxDistance + 2;
        $previousPrevious = [];
        $previous = array_fill(0, $m + 1, $infinity);
        for ($j = 0; $j <= min($m, $maxDistance); $j++) {
            $previous[$j] = $j;
        }

        for ($i = 1; $i <= $n; $i++) {
            $current = array_fill(0, $m + 1, $infinity);
            if ($i <= $maxDistance) {
                $current[0] = $i;
            }

            $from = max(1, $i - $maxDistance);
            $to = min($m, $i + $maxDistance);
            for ($j = $from; $j <= $to; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $distance = min(
                    $previous[$j] + 1,
                    $current[$j - 1] + 1,
                    $previous[$j - 1] + $cost
                );

                if ($i > 1 && $j > 1
                    && $a[$i - 1] === $b[$j - 2]
                    && $a[$i - 2] === $b[$j - 1]) {
                    $distance = min($distance, $previousPrevious[$j - 2] + 1);
                }

                $current[$j] = min($distance, $infinity);
            }

            $previousPrevious = $previous;
            $previous = $current;
        }

        return $previous[$m] <= $maxDistance ? $previous[$m] : null;
    }
}
