<?php

declare(strict_types=1);

namespace Uganda\Import;

use JsonException;
use RuntimeException;

final class LegacyJsonImporter
{
    private const LEVELS = [
        'district' => ['file' => 'districts.json', 'parent' => null],
        'county' => ['file' => 'counties.json', 'parent' => 'district'],
        'subcounty' => ['file' => 'sub_counties.json', 'parent' => 'county'],
        'parish' => ['file' => 'parishes.json', 'parent' => 'subcounty'],
        'village' => ['file' => 'villages.json', 'parent' => 'parish'],
    ];

    /**
     * Preserve source identifiers and every row without normalizing name keys.
     *
     * @return array<string, mixed>
     */
    public function import(string $directory): array
    {
        $units = [];
        $ids = [];

        foreach (self::LEVELS as $level => $definition) {
            $path = rtrim($directory, '/') . '/' . $definition['file'];
            if (!is_file($path) || !is_readable($path)) {
                throw new RuntimeException("Missing or unreadable source file: $path");
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException("Unable to read source file: $path");
            }

            try {
                $rows = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("Invalid JSON in $path", 0, $exception);
            }

            if (!is_array($rows)) {
                throw new RuntimeException("Expected array of rows in $path");
            }

            foreach ($rows as $index => $row) {
                if (!is_array($row) || !isset($row['id'], $row['name']) ||
                    (!is_string($row['id']) && !is_int($row['id'])) ||
                    !is_string($row['name']) || trim($row['name']) === '') {
                    throw new RuntimeException("Invalid $level source record at row $index");
                }

                $sourceId = (string) $row['id'];
                if ($sourceId === '') {
                    throw new RuntimeException("Empty $level identifier at row $index");
                }

                $id = "legacy:$level:$sourceId";
                if (isset($ids[$id])) {
                    throw new RuntimeException("Duplicate source identifier $id");
                }
                $ids[$id] = true;

                $parentLevel = $definition['parent'];
                $parentId = null;
                if ($parentLevel !== null) {
                    if (!isset($row[$parentLevel]) ||
                        (!is_string($row[$parentLevel]) && !is_int($row[$parentLevel])) ||
                        (string) $row[$parentLevel] === '') {
                        throw new RuntimeException("Missing $parentLevel reference in $id");
                    }
                    $parentId = 'legacy:' . $parentLevel . ':' . (string) $row[$parentLevel];
                }

                $units[] = [
                    'id' => $id,
                    'level' => $level,
                    'name' => $row['name'],
                    'parent_id' => $parentId,
                    'source_id' => 'legacy-bundled',
                    'source_record_id' => $sourceId,
                    'aliases' => [],
                    'lifecycle_status' => 'active',
                    'external_codes' => [],
                    'verification_status' => 'unverified',
                ];
            }
        }

        return [
            'schema_version' => 1,
            'snapshot_id' => 'legacy-bundled-baseline',
            'source_ids' => ['legacy-bundled'],
            'reference_date' => null,
            'units' => $units,
        ];
    }
}
