<?php

declare(strict_types=1);

namespace Uganda\Data;

final class CanonicalIdentityCrosswalk
{
    /**
     * Validate explicit identity decisions without using place names as keys.
     *
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $links
     * @return array<string, mixed>
     */
    public function validate(array $snapshot, array $links): array
    {
        $errors = [];
        $units = $snapshot['units'] ?? null;
        if (!is_array($units)) {
            return ['valid' => false, 'errors' => ['Missing snapshot units'],
                'verified_ids' => [], 'unresolved_ids' => [], 'unmapped_ids' => []];
        }
        $byId = [];
        foreach ($units as $index => $unit) {
            $id = is_array($unit) ? ($unit['id'] ?? null) : null;
            if (!is_string($id) || $id === '' || isset($byId[$id])) {
                $errors[] = "Invalid or duplicate snapshot identity at index $index";
                continue;
            }
            $byId[$id] = true;
        }
        $seen = [];
        $canonical = [];
        $verified = [];
        $unresolved = [];
        foreach ($links as $index => $link) {
            $legacy = $link['legacy_id'] ?? null;
            $target = $link['canonical_id'] ?? null;
            $status = $link['status'] ?? null;
            if (!is_string($legacy) || !isset($byId[$legacy]) || isset($seen[$legacy])) {
                $errors[] = "Unrecognized or duplicate legacy ID at link $index";
                continue;
            }
            $seen[$legacy] = true;
            if ($status === 'unresolved') {
                if ($target !== null) {
                    $errors[] = "Unresolved link $index cannot claim identity";
                }
                $unresolved[] = $legacy;
                continue;
            }
            $url = $link['evidence_url'] ?? null;
            $date = $link['evidence_date'] ?? null;
            $dateValue = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            if ($status !== 'verified' || !is_string($target) || $target === '' ||
                !is_string($url) || strpos($url, 'https://') !== 0 ||
                $dateValue === false || $dateValue->format('Y-m-d') !== $date) {
                $errors[] = "Unverified identity claim at link $index";
                continue;
            }
            if (isset($canonical[$target]) && $canonical[$target] !== $legacy) {
                $errors[] = "Canonical ID $target has conflicting legacy mappings";
            }
            $canonical[$target] = $legacy;
            $verified[] = $legacy;
        }
        $unmapped = array_keys(array_diff_key($byId, $seen));
        sort($unmapped);
        sort($verified);
        sort($unresolved);
        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'verified_ids' => $verified,
            'unresolved_ids' => $unresolved,
            'unmapped_ids' => $unmapped,
            'total_source_units' => count($units),
        ];
    }
}
