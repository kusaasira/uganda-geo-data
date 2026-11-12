<?php

declare(strict_types=1);

namespace Tests\Unit;

use Normalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Uganda;

final class FuzzyPublicApiTest extends TestCase
{
    #[Test]
    public function public_unicode_and_fuzzy_methods_keep_the_same_source_models(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('optional ext-intl is required');
        }

        $directory = sys_get_temp_dir() . '/geo-fuzzy-api-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $tables = [
            'districts' => [
                ['id' => '1', 'name' => 'District One'],
            ],
            'counties' => [
                ['id' => '2', 'name' => 'Mukono', 'district' => '1'],
            ],
            'sub_counties' => [
                ['id' => '3', 'name' => 'Kampala', 'county' => '2'],
            ],
            'parishes' => [
                ['id' => '4', 'name' => 'São Tomé', 'subcounty' => '3'],
                ['id' => '5', 'name' => 'SAO TOME', 'subcounty' => '3'],
            ],
            'villages' => [
                ['id' => '6', 'name' => 'Kampala', 'parish' => '4'],
                ['id' => '7', 'name' => 'Kampala', 'parish' => '4'],
                ['id' => '8', 'name' => 'Kampalaa', 'parish' => '4'],
                ['id' => '9', 'name' => 'Another', 'parish' => '5'],
            ],
        ];
        try {
            foreach ($tables as $filename => $rows) {
                file_put_contents($directory . '/' . $filename . '.json', json_encode($rows, JSON_THROW_ON_ERROR));
            }

            $geo = new Uganda($directory);
            $accented = $geo->searchByUnicodeName('parish', 'SAO TOME', 3, true);
            self::assertSame([4, 5], array_map(static fn ($unit): int => $unit->id(), $accented));

            $names = $geo->searchByUnicodePrefix('parish', 'sao', 3, 2, true);
            self::assertSame([4, 5], array_map(static fn ($unit): int => $unit->id(), $names));

            $suggestions = $geo->searchFuzzyWithScores('village', 'kapmala', 4, 3, 1);
            self::assertSame([6, 7], array_map(
                static fn (array $suggestion): int => $suggestion['unit']->id(),
                $suggestions
            ));
            self::assertSame([1, 1], array_column($suggestions, 'distance'));
            self::assertSame(
                array_column($suggestions, 'unit'),
                $geo->searchFuzzy('village', 'kapmala', 4, 3, 1)
            );
            self::assertSame($geo->findById('village', 6), $suggestions[0]['unit']);

            // Unicode opt-in does not silently alter historical ASCII matching.
            self::assertSame([5], array_map(
                static fn ($unit): int => $unit->id(),
                $geo->searchByName('parish', 'SAO TOME')
            ));
        } finally {
            foreach (glob($directory . '/*.json') ?: [] as $filename) {
                unlink($filename);
            }
            rmdir($directory);
        }
    }

    #[Test]
    public function empty_query_unsupported_level_and_oversized_input_fail_closed(): void
    {
        if (!class_exists(Normalizer::class)) {
            self::markTestSkipped('optional ext-intl is required');
        }
        $geo = new Uganda();
        $invalid = [
            static fn () => $geo->searchByUnicodeName('province', 'Kampala'),
            static fn () => $geo->searchByUnicodeName('district', '  '),
            static fn () => $geo->searchByUnicodePrefix('district', "\u{00AD}"),
            static fn () => $geo->searchByUnicodeName('district', str_repeat('a', 129)),
            static fn () => $geo->searchByUnicodeName('district', "\xC3\x28"),
            static fn () => $geo->searchFuzzy('village', 'Kampala', null, 0),
            static fn () => $geo->searchFuzzy('village', 'Kampala', null, 101),
            static fn () => $geo->searchFuzzy('village', 'Kampala', null, 10, 4),
            static fn () => $geo->searchByUnicodePrefix('district', 'Ka', null, 0),
        ];

        foreach ($invalid as $index => $callback) {
            try {
                $callback();
                self::fail('Invalid Unicode query accepted: case=' . $index);
            } catch (\InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }
}
