<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class PublicationCountComparator
{
    /**
     * Compare dated statistical denominators without implying legal boundary changes.
     *
     * @param array<string, mixed> $older
     * @param array<string, mixed> $newer
     * @param array<int, mixed> $mappings
     * @return array<string, mixed>
     */
    public function compare(array $older, array $newer, array $mappings): array
    {
        if (!isset($older['id'], $newer['id'], $older['counts'], $newer['counts']) ||
            !is_array($older['counts']) || !is_array($newer['counts'])) {
            throw new InvalidArgumentException('Both publications require identifiers and count dictionaries');
        }
        if ($mappings === [] || array_keys($mappings) !== array_keys(array_values($mappings))) {
            throw new InvalidArgumentException('Publication comparisons require a nonempty mapping list');
        }
        $output = [];
        $seen = [];
        foreach ($mappings as $index => $mapping) {
            if (!is_array($mapping)) {
                throw new InvalidArgumentException("Invalid comparison mapping at index $index");
            }
            $a = $mapping['older'] ?? null;
            $b = $mapping['newer'] ?? null;
            if (!is_string($a) || !is_string($b) || !isset($older['counts'][$a], $newer['counts'][$b])) {
                throw new InvalidArgumentException("Missing reference category in comparison at index $index");
            }
            $key = $a . ':' . $b;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Duplicate publication category comparison at index $index");
            }
            $seen[$key] = true;
            $previous = $older['counts'][$a];
            $current = $newer['counts'][$b];
            if (!is_int($previous) || !is_int($current) || $previous < 0 || $current < 0 ||
                !isset($mapping['scope_verified']) || !is_bool($mapping['scope_verified'])) {
                throw new InvalidArgumentException("Invalid reference counts or scope declaration at index $index");
            }
            $output[] = [
                'older_category' => $a,
                'newer_category' => $b,
                'older_count' => $previous,
                'newer_count' => $current,
                'arithmetic_difference' => $current - $previous,
                'scope_verified' => $mapping['scope_verified'],
                'administrative_change_verified' => false,
            ];
        }
        return [
            'older_reference' => $older['id'],
            'newer_reference' => $newer['id'],
            'comparisons' => $output,
            'status' => 'not_established',
            'explanation' => 'A statistical denominator difference is not proof of any unit-level administrative change.',
        ];
    }
}
