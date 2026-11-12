<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\SeededHierarchyFixture;
use Uganda\Uganda;

require_once dirname(__DIR__) . '/support/SeededHierarchyFixture.php';

final class HierarchyMutationPropertyTest extends TestCase
{
    #[Test]
    public function a_mutated_parent_link_at_any_level_never_discards_an_orphan(): void
    {
        $parents = [
            ['villages', 'parish'], ['parishes', 'subcounty'],
            ['sub_counties', 'county'], ['counties', 'district'],
        ];

        for ($seed = 1; $seed <= 96; $seed++) {
            $random = new SeededHierarchyFixture($seed * 1009);
            [$level, $field] = $parents[$random->nextInt(0, count($parents) - 1)];
            $rows = $this->validRows();
            $rows[$level][0][$field] = (string) (100000 + $seed);
            $this->assertInvalidHierarchy($rows, "Orphan", "seed=$seed level=$level");
        }
    }

    #[Test]
    public function duplicate_numeric_aliases_never_overwrite_records_at_any_level(): void
    {
        foreach (['districts', 'counties', 'sub_counties', 'parishes', 'villages'] as $level) {
            for ($seed = 1; $seed <= 12; $seed++) {
                $rows = $this->validRows();
                $duplicate = $rows[$level][0];
                $duplicate['id'] = str_repeat('0', ($seed % 8) + 1) . $duplicate['id'];
                $duplicate['name'] = 'Collision ' . $seed;
                $rows[$level][] = $duplicate;
                $this->assertInvalidHierarchy($rows, 'Duplicate', "seed=$seed level=$level");
            }
        }
    }

    /** @return array<string, array<int, array<string, string>>> */
    private function validRows(): array
    {
        return [
            'districts' => [['id' => '1', 'name' => 'District']],
            'counties' => [['id' => '2', 'name' => 'County', 'district' => '1']],
            'sub_counties' => [['id' => '3', 'name' => 'Subcounty', 'county' => '2']],
            'parishes' => [['id' => '4', 'name' => 'Parish', 'subcounty' => '3']],
            'villages' => [['id' => '5', 'name' => 'Village', 'parish' => '4']],
        ];
    }

    /** @param array<string, array<int, array<string, string>>> $rows */
    private function assertInvalidHierarchy(array $rows, string $expected, string $label): void
    {
        $folder = sys_get_temp_dir() . '/geo-mutation-' . bin2hex(random_bytes(6));
        mkdir($folder);
        try {
            foreach ($rows as $file => $records) {
                file_put_contents($folder . '/' . $file . '.json', json_encode($records, JSON_THROW_ON_ERROR));
            }
            try {
                new Uganda($folder);
                self::fail("Invalid hierarchy was accepted: $label");
            } catch (\UnexpectedValueException $exception) {
                self::assertStringContainsString($expected, $exception->getMessage(), $label);
            }
        } finally {
            foreach (glob($folder . '/*.json') ?: [] as $file) {
                unlink($file);
            }
            rmdir($folder);
        }
    }
}
