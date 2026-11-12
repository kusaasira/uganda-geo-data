<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use Normalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Lookup\UnicodeNameNormalizer;

final class UnicodeNameNormalizerTest extends TestCase
{
    #[Test]
    public function canonical_equivalents_case_folds_and_compatibility_forms_share_a_key(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('Full Unicode normalization requires the optional intl extension');
        }

        self::assertSame(
            UnicodeNameNormalizer::normalize('École'),
            UnicodeNameNormalizer::normalize("E\u{0301}COLE")
        );
        self::assertSame('strasse', UnicodeNameNormalizer::normalize('Straße'));
        self::assertSame('strasse', UnicodeNameNormalizer::normalize('STRASSE'));
        self::assertSame('mukono', UnicodeNameNormalizer::normalize('ＭＵＫＯＮＯ'));
        self::assertSame('ffi', UnicodeNameNormalizer::normalize('ﬃ'));
        self::assertSame(
            UnicodeNameNormalizer::normalize('Σ'),
            UnicodeNameNormalizer::normalize('ς')
        );
        self::assertSame(
            UnicodeNameNormalizer::normalize('ПРИВЕТ'),
            UnicodeNameNormalizer::normalize('привет')
        );
    }

    #[Test]
    public function diacritic_folding_is_explicit_and_does_not_modify_display_names(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('intl required');
        }

        self::assertNotSame(
            UnicodeNameNormalizer::normalize('São Tomé'),
            UnicodeNameNormalizer::normalize('Sao Tome')
        );
        self::assertSame('sao tome', UnicodeNameNormalizer::normalize('São Tomé', true));
        self::assertSame('sao tome', UnicodeNameNormalizer::normalize("Sa\u{0303}o To\u{0301}me", true));
        self::assertSame('i', UnicodeNameNormalizer::normalize('İ', true));
        self::assertSame('i̇', UnicodeNameNormalizer::normalize('İ'));
        self::assertSame('new town', UnicodeNameNormalizer::normalize("  ＮＥＷ\u{00A0}\tTOWN  "));
    }

    #[Test]
    public function every_unicode_white_space_separator_has_the_same_search_key(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('intl required');
        }

        foreach (["\u{0085}", "\u{1680}", "\u{2007}", "\u{2028}", "\u{2029}", "\u{205F}"] as $separator) {
            self::assertSame(
                'new town',
                UnicodeNameNormalizer::normalize("  NEW{$separator}TOWN{$separator}"),
                'Unicode whitespace normalization must include all White_Space categories'
            );
        }
    }

    #[Test]
    public function malformed_utf8_is_rejected_before_any_index_can_be_built(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UnicodeNameNormalizer::normalize("\xC3\x28");
    }

    #[Test]
    public function grapheme_boundaries_do_not_split_flags_or_combining_sequences(): void
    {
        self::assertCount(1, UnicodeNameNormalizer::graphemes("e\u{0301}"));
        self::assertCount(1, UnicodeNameNormalizer::graphemes('🇺🇬'));
        self::assertCount(1, UnicodeNameNormalizer::graphemes('👩‍💻'));
        self::assertSame(['🇺🇬', '🇰🇪'], UnicodeNameNormalizer::graphemes('🇺🇬🇰🇪'));
    }

    #[Test]
    public function missing_intl_support_is_a_precise_runtime_contract(): void
    {
        if (class_exists(Normalizer::class)) {
            self::assertTrue(class_exists(Normalizer::class));
            return;
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ext-intl');
        UnicodeNameNormalizer::normalize('Kampala');
    }
}
