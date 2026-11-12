<?php

declare(strict_types=1);

namespace Uganda\Lookup;

use InvalidArgumentException;
use Normalizer;
use RuntimeException;

/**
 * Unicode-aware search normalization deliberately lives outside the legacy
 * ASCII exact-name path. Canonical model names and source IDs are unchanged.
 *
 * ICU's NFKC_Casefold handles compatibility forms, full Unicode case folding
 * and canonical-equivalent input. No transliteration or confusable folding.
 */
final class UnicodeNameNormalizer
{
    public static function requireIntl(): void
    {
        if (!class_exists(Normalizer::class) || !defined('Normalizer::FORM_KC_CF')) {
            throw new RuntimeException(
                'Unicode-aware and fuzzy location searches require the optional PHP intl extension (ext-intl)'
            );
        }
    }

    public static function normalize(string $text, bool $ignoreDiacritics = false): string
    {
        if (preg_match('//u', $text) !== 1) {
            throw new InvalidArgumentException('Location search text must contain valid UTF-8');
        }

        self::requireIntl();

        $folded = Normalizer::normalize($text, Normalizer::FORM_KC_CF);
        if ($folded === false) {
            throw new RuntimeException('ICU could not normalize location search text');
        }

        if ($ignoreDiacritics) {
            $decomposed = Normalizer::normalize($folded, Normalizer::FORM_D);
            if ($decomposed === false) {
                throw new RuntimeException('ICU could not decompose location search text');
            }
            $unmarked = preg_replace('/\p{M}+/u', '', $decomposed);
            if ($unmarked === null) {
                throw new RuntimeException('Unable to remove Unicode combining marks');
            }
            $folded = Normalizer::normalize($unmarked, Normalizer::FORM_C);
            if ($folded === false) {
                throw new RuntimeException('ICU could not recompose location search text');
            }
        }

        // All whitespace separators are search-equivalent. Zero-width format
        // characters are handled by NFKC_Casefold, not stripped arbitrarily.
        $collapsed = preg_replace('/[\p{Z}\x{0009}-\x{000D}]+/u', ' ', $folded);
        if ($collapsed === null) {
            throw new RuntimeException('Unable to normalize location search whitespace');
        }

        return trim($collapsed);
    }

    /** @return array<int, string> */
    public static function graphemes(string $normalized): array
    {
        if (preg_match_all('/\X/u', $normalized, $matches) === false) {
            throw new InvalidArgumentException('Cannot segment invalid UTF-8 into grapheme clusters');
        }

        return $matches[0];
    }
}
