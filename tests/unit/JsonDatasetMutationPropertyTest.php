<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\SeededHierarchyFixture;
use Uganda\Data\JsonDatasetLoader;

require_once dirname(__DIR__) . '/support/SeededHierarchyFixture.php';

final class JsonDatasetMutationPropertyTest extends TestCase
{
    #[Test]
    public function invalid_record_shapes_and_identifiers_fail_closed_for_reproducible_seeds(): void
    {
        $badValues = [
            null, false, true, [], ['nested'], 0, -1, 3.14, '',
            '0', '0000', '-1', '+1', '1.1', '1e3', 'NaN',
            str_repeat('9', 40),
        ];
        $fields = ['id', 'name', 'parish'];
        $loader = new JsonDatasetLoader();
        $path = tempnam(sys_get_temp_dir(), 'villages.json.');
        self::assertNotFalse($path);

        // The loader determines parent schemas from the filename.
        $folder = sys_get_temp_dir() . '/geo-property-loader-' . bin2hex(random_bytes(6));
        mkdir($folder);
        $dataset = $folder . '/villages.json';

        try {
            for ($seed = 1; $seed <= 128; $seed++) {
                $random = new SeededHierarchyFixture($seed * 4093);
                $field = $fields[$random->nextInt(0, count($fields) - 1)];
                $invalid = $badValues[$random->nextInt(0, count($badValues) - 1)];
                $row = ['id' => '1', 'name' => 'Central', 'parish' => '2'];

                if ($field === 'name') {
                    // Numeric strings remain legitimate place names, whereas
                    // unsupported name shapes and blank strings do not.
                    $invalid = [null, false, true, [], 0, 1.0, ''][$random->nextInt(0, 6)];
                } elseif (($field === 'id' || $field === 'parish') && $invalid === null) {
                    unset($row[$field]);
                }
                if (array_key_exists($field, $row)) {
                    $row[$field] = $invalid;
                }

                file_put_contents($dataset, json_encode([$row], JSON_THROW_ON_ERROR));
                try {
                    $loader->load($dataset);
                    self::fail("Invalid source field accepted: seed=$seed field=$field");
                } catch (RuntimeException $exception) {
                    self::assertNotSame('', $exception->getMessage(), "seed=$seed field=$field");
                }
            }
        } finally {
            unlink($dataset);
            rmdir($folder);
            unlink($path);
        }
    }

    #[Test]
    public function invalid_utf8_json_and_invalid_top_level_collections_are_rejected(): void
    {
        $folder = sys_get_temp_dir() . '/geo-json-adversarial-' . bin2hex(random_bytes(6));
        mkdir($folder);
        $file = $folder . '/villages.json';
        try {
            foreach ([
                '{"id":"1","name":"Central","parish":"2"}',
                '[]',
                '{"rows":[{"id":"1","name":"Central","parish":"2"}]}',
                '[{"id":"1","name":"' . "\xC3\x28" . '","parish":"2"}]',
                '[{"id":"1","name":"Central","parish":"2"},',
            ] as $case => $raw) {
                file_put_contents($file, $raw);
                try {
                    (new JsonDatasetLoader())->load($file);
                    self::fail("Malformed JSON input accepted: case=$case");
                } catch (RuntimeException $exception) {
                    self::assertNotSame('', $exception->getMessage());
                }
            }
        } finally {
            unlink($file);
            rmdir($folder);
        }
    }

    #[Test]
    public function valid_zero_padded_parent_ids_are_preserved_as_source_text(): void
    {
        $folder = sys_get_temp_dir() . '/geo-json-ids-' . bin2hex(random_bytes(6));
        mkdir($folder);
        $file = $folder . '/villages.json';
        try {
            file_put_contents($file, json_encode([
                ['id' => '0005', 'name' => 'Central', 'parish' => '0002'],
            ], JSON_THROW_ON_ERROR));
            $rows = (new JsonDatasetLoader())->load($file);
            self::assertSame('0005', $rows[0]['id']);
            self::assertSame('0002', $rows[0]['parish']);
        } finally {
            unlink($file);
            rmdir($folder);
        }
    }
}
