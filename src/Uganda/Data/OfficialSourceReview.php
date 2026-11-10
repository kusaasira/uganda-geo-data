<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class OfficialSourceReview
{
    /**
     * Show which official publications can support administrative-unit identity.
     * Aggregate statistical reporting does not establish current legal boundaries.
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function review(array $manifest): array
    {
        $publications = $manifest['publications'] ?? null;
        if (!is_array($publications) || $publications === []) {
            throw new InvalidArgumentException('Source review requires a nonempty publications list');
        }

        $ids = [];
        $results = [];
        foreach ($publications as $index => $source) {
            if (!is_array($source) || !isset($source['id'], $source['publisher'], $source['url'],
                $source['title'], $source['scope'], $source['status'])) {
                throw new InvalidArgumentException("Publication at index $index is incomplete");
            }
            foreach (['id', 'publisher', 'url', 'title', 'scope', 'status'] as $field) {
                if (!is_string($source[$field]) || trim($source[$field]) === '') {
                    throw new InvalidArgumentException("Publication at index $index has invalid $field");
                }
            }
            $id = $source['id'];
            if (isset($ids[$id])) {
                throw new InvalidArgumentException("Duplicate publication ID $id");
            }
            $ids[$id] = true;
            if (strpos($source['url'], 'https://') !== 0) {
                throw new InvalidArgumentException("Publication $id must have an HTTPS URL");
            }
            $year = $source['reference_year'] ?? null;
            if ($year !== null && (!is_int($year) || $year < 1900 || $year > 2100)) {
                throw new InvalidArgumentException("Invalid reference year for $id");
            }
            foreach (['publication_date', 'legal_effective_date'] as $dateKey) {
                $date = $source[$dateKey] ?? null;
                if ($date !== null && (!is_string($date) ||
                    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
                    !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2),
                        (int) substr($date, 0, 4)))) {
                    throw new InvalidArgumentException("Invalid $dateKey for $id");
                }
            }

            if (!is_bool($source['unit_level_identity_verified'] ?? null)) {
                throw new InvalidArgumentException("Publication $id requires a boolean identity-verification flag");
            }
            $canResolveIdentity = $source['unit_level_identity_verified'] && $source['legal_effective_date'] !== null;
            $results[] = [
                'id' => $id,
                'reference_year' => $year,
                'publication_date' => $source['publication_date'] ?? null,
                'source_role' => $source['status'],
                'scope' => $source['scope'],
                'unit_identity_ready' => $canResolveIdentity,
                'requires_source_crosswalk' => !$canResolveIdentity,
                'legal_effective_date' => $source['legal_effective_date'] ?? null,
                'url' => $source['url'],
            ];
        }

        return [
            'source_count' => count($results),
            'identity_ready_sources' => count(array_filter($results, static function (array $row): bool {
                return $row['unit_identity_ready'];
            })),
            'publications' => $results,
        ];
    }
}
