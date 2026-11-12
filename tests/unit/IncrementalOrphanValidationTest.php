<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Uganda\Uganda;

final class IncrementalOrphanValidationTest extends TestCase
{
    /** @test */
    public function itRejectsAnOrphanAtEveryAdministrativeLevel(): void
    {
        $base = [
            'districts' => [['id' => '1', 'name' => 'District']],
            'counties' => [['id' => '2', 'name' => 'County', 'district' => '1']],
            'sub_counties' => [['id' => '3', 'name' => 'Subcounty', 'county' => '2']],
            'parishes' => [['id' => '4', 'name' => 'Parish', 'subcounty' => '3']],
            'villages' => [['id' => '5', 'name' => 'Village', 'parish' => '4']],
        ];

        foreach ([
            ['villages', 'parish', 'Orphan village record 5'],
            ['parishes', 'subcounty', 'Orphan parish record 4'],
            ['sub_counties', 'county', 'Orphan subcounty record 3'],
            ['counties', 'district', 'Orphan county record 2'],
        ] as [$level, $parentKey, $error]) {
            $directory = sys_get_temp_dir() . '/uganda-geo-orphans-' . bin2hex(random_bytes(6));
            mkdir($directory);
            try {
                $rows = $base;
                $rows[$level][0][$parentKey] = '999';
                foreach ($rows as $table => $entries) {
                    file_put_contents($directory . '/' . $table . '.json', json_encode($entries, JSON_THROW_ON_ERROR));
                }
                try {
                    new Uganda($directory);
                    self::fail('A missing parent cannot be accepted');
                } catch (\UnexpectedValueException $exception) {
                    self::assertStringContainsString($error, $exception->getMessage());
                }
            } finally {
                foreach (glob($directory . '/*.json') ?: [] as $file) {
                    unlink($file);
                }
                rmdir($directory);
            }
        }
    }
}
