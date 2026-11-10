<?php

declare(strict_types=1);

namespace Uganda\Data;

use JsonException;
use RuntimeException;

final class JsonDatasetLoader
{
    /**
     * Fail closed when required packaged administrative data is unreadable or invalid.
     *
     * @return array<int, array<string, int|string>>
     */
    public function load(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Missing or unreadable dataset: ' . $path);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read dataset: ' . $path);
        }

        try {
            $rows = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid JSON in ' . $path, 0, $exception);
        }

        if (!is_array($rows) || $rows === [] || array_keys($rows) !== array_keys(array_values($rows))) {
            throw new RuntimeException('Expected a nonempty list of dataset records: ' . $path);
        }

        $requiredParents = [
            'villages.json' => 'parish',
            'parishes.json' => 'subcounty',
            'sub_counties.json' => 'county',
            'counties.json' => 'district',
        ];
        $parentKey = $requiredParents[basename($path)] ?? null;

        foreach ($rows as $index => $row) {
            if (!is_array($row) || !isset($row['id'], $row['name']) ||
                (!is_string($row['id']) && !is_int($row['id'])) ||
                (string) $row['id'] === '' ||
                !is_string($row['name']) || trim($row['name']) === '') {
                throw new RuntimeException("Invalid dataset record at index $index in $path");
            }

            if (!$this->isPositiveNativeId($row['id'])) {
                throw new RuntimeException("Invalid numeric ID at index $index in $path");
            }
            if ($parentKey !== null) {
                $parent = $row[$parentKey] ?? null;
                if (!$this->isPositiveNativeId($parent)) {
                    throw new RuntimeException("Invalid $parentKey parent ID at index $index in $path");
                }
            }

            foreach ($row as $value) {
                if (!is_string($value) && !is_int($value)) {
                    throw new RuntimeException("Invalid dataset record at index $index in $path");
                }
            }
        }

        return $rows;
    }

    /**
     * PHP model identifiers are native integers. Reject overflow before casting,
     * otherwise multiple distinct source IDs may collapse into PHP_INT_MAX.
     *
     * @param mixed $value
     */
    private function isPositiveNativeId($value): bool
    {
        if ((!is_int($value) && !is_string($value)) || !ctype_digit((string) $value)) {
            return false;
        }
        $decimal = ltrim((string) $value, '0');
        if ($decimal === '') {
            return false;
        }

        $max = (string) PHP_INT_MAX;
        return strlen($decimal) < strlen($max) ||
            (strlen($decimal) === strlen($max) && strcmp($decimal, $max) <= 0);
    }
}
