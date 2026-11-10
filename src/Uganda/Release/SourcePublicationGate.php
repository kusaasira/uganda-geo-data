<?php

declare(strict_types=1);

namespace Uganda\Release;

use InvalidArgumentException;

final class SourcePublicationGate
{
    /**
     * Publicly accessible endpoints may be acquired for permitted development
     * use. Distribution of source geometry requires explicit source approval.
     *
     * @param array<string, mixed> $registry
     * @param array<int, string> $sourceIds
     */
    public function assertMayPublish(array $registry, array $sourceIds): void
    {
        if ($sourceIds === []) {
            throw new InvalidArgumentException('Release sources must be specified');
        }
        $sources = [];
        $entries = $registry['sources'] ?? null;
        if (!is_array($entries)) {
            throw new InvalidArgumentException('Invalid source registry inventory');
        }
        foreach ($entries as $source) {
            if (is_array($source) && is_string($source['id'] ?? null)) {
                $sources[$source['id']] = $source;
            }
        }
        $seen = [];
        foreach ($sourceIds as $id) {
            if (!is_string($id) || $id === '' || isset($seen[$id])) {
                throw new InvalidArgumentException('Invalid or duplicate source ID in release request');
            }
            $seen[$id] = true;
            $source = $sources[$id] ?? null;
            if ($source === null || ($source['redistribution_status'] ?? null) !== 'permitted'
                || !is_string($source['license_url'] ?? null)
                || !str_starts_with($source['license_url'], 'https://')
                || !is_string($source['attribution'] ?? null)
                || trim($source['attribution']) === '') {
                throw new InvalidArgumentException('Source has no verified distribution grant: ' . $id);
            }
        }
    }
}
