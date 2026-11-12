<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Uganda\Uganda;

final class VillageEnumerationFastPathTest extends TestCase
{
    #[Test]
    public function sparse_source_identifiers_remain_in_original_order_after_native_batch_append(): void
    {
        $folder = sys_get_temp_dir() . '/geo-village-flatten-' . bin2hex(random_bytes(6));
        mkdir($folder);
        $sources = [
            'districts' => [['id' => '1', 'name' => 'District']],
            'counties' => [['id' => '4', 'name' => 'County', 'district' => '1']],
            'sub_counties' => [['id' => '5', 'name' => 'Subcounty', 'county' => '4']],
            'parishes' => [
                ['id' => '9', 'name' => 'Parish A', 'subcounty' => '5'],
                ['id' => '8', 'name' => 'Parish B', 'subcounty' => '5'],
            ],
            'villages' => [
                ['id' => '91', 'name' => 'Central', 'parish' => '9'],
                ['id' => '2', 'name' => 'Central', 'parish' => '9'],
                ['id' => '45', 'name' => 'Central', 'parish' => '8'],
                ['id' => '101', 'name' => 'Other', 'parish' => '8'],
            ],
        ];
        try {
            foreach ($sources as $name => $rows) {
                file_put_contents($folder . '/' . $name . '.json', json_encode($rows, JSON_THROW_ON_ERROR));
            }

            $geo = new Uganda($folder);
            $flattened = $geo->villages();
            $streamed = iterator_to_array($geo->iterate('village'), false);

            self::assertTrue(array_is_list($flattened));
            self::assertSame([91, 2, 45, 101], array_map(
                static fn ($village): int => $village->id(),
                $flattened
            ));
            self::assertSame($streamed, $flattened);
            self::assertSame($flattened, $geo->villages());

            foreach ($flattened as $village) {
                self::assertSame($village, $geo->findById('village', $village->id()));
            }
            self::assertCount(3, $geo->searchByName('village', 'Central'));
        } finally {
            foreach (glob($folder . '/*.json') ?: [] as $file) {
                unlink($file);
            }
            rmdir($folder);
        }
    }

    #[Test]
    public function production_village_enumeration_matches_the_unflattened_object_stream(): void
    {
        $geo = new Uganda();
        self::assertSame(
            iterator_to_array($geo->iterate('village'), false),
            $geo->villages()
        );
    }
}
