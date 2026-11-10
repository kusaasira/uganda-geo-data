<?php

declare(strict_types=1);

namespace Uganda\Data;

use InvalidArgumentException;

final class LegacyIdentityCrosswalk
{
    /**
     * Generate one traceable mapping record per legacy source unit. Approved
     * external mappings require explicit proof; no name-based matches are made.
     *
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $decisions
     * @return array<string, mixed>
     */
    public function build(array $snapshot, array $decisions = []): array
    {
        if (!isset($snapshot['units']) || !is_array($snapshot['units'])) {
            throw new InvalidArgumentException('Canonical snapshot requires a units list');
        }

        $legacyIds = [];
        foreach ($snapshot['units'] as $index => $unit) {
            if (!is_array($unit) || !isset($unit['id'], $unit['level'], $unit['name'],
                $unit['source_record_id'], $unit['source_id']) ||
                !is_string($unit['id']) || strpos($unit['id'], 'legacy:') !== 0 ||
                !is_string($unit['level']) || !is_string($unit['name']) ||
                !is_string($unit['source_record_id']) || !is_string($unit['source_id'])) {
                throw new InvalidArgumentException("Invalid legacy unit at index $index");
            }
            $id = $unit['id'];
            if (isset($legacyIds[$id])) {
                throw new InvalidArgumentException("Duplicate legacy ID $id");
            }
            $legacyIds[$id] = $unit;
        }

        $byLegacy = [];
        $seenTargets = [];
        foreach ($decisions as $index => $decision) {
            if (!is_array($decision) || !isset($decision['legacy_id'], $decision['canonical_id'],
                $decision['evidence_url'], $decision['evidence_date'], $decision['reviewer']) ||
                !is_string($decision['legacy_id']) || !is_string($decision['canonical_id']) ||
                !is_string($decision['evidence_url']) || !is_string($decision['evidence_date']) ||
                !is_string($decision['reviewer'])) {
                throw new InvalidArgumentException("Invalid identity decision at index $index");
            }

            $legacy = $decision['legacy_id'];
            $canonical = $decision['canonical_id'];
            if (!isset($legacyIds[$legacy])) {
                throw new InvalidArgumentException("Unknown legacy ID $legacy");
            }
            if (isset($byLegacy[$legacy])) {
                throw new InvalidArgumentException("Conflicting decisions for $legacy");
            }
            if (!preg_match('/^[A-Za-z0-9:_-]+$/', $canonical) || $canonical === '') {
                throw new InvalidArgumentException("Invalid canonical identifier for $legacy");
            }
            if (isset($seenTargets[$canonical])) {
                throw new InvalidArgumentException("Many-to-one mapping requires an explicit reviewed merge: $canonical");
            }
            if (strpos($decision['evidence_url'], 'https://') !== 0 ||
                trim($decision['reviewer']) === '' ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $decision['evidence_date']) ||
                !checkdate((int) substr($decision['evidence_date'], 5, 2),
                    (int) substr($decision['evidence_date'], 8, 2),
                    (int) substr($decision['evidence_date'], 0, 4))) {
                throw new InvalidArgumentException("Verified mapping for $legacy requires dated HTTPS evidence and reviewer");
            }
            $byLegacy[$legacy] = $decision;
            $seenTargets[$canonical] = $legacy;
        }

        $rows = [];
        foreach ($legacyIds as $id => $unit) {
            $decision = $byLegacy[$id] ?? null;
            $rows[] = [
                'legacy_id' => $id,
                'legacy_source_id' => $unit['source_record_id'],
                'source_id' => $unit['source_id'],
                'level' => $unit['level'],
                'name' => $unit['name'],
                'parent_legacy_id' => $unit['parent_id'] ?? null,
                'canonical_id' => $decision['canonical_id'] ?? null,
                'verification_status' => $decision === null ? 'unverified' : 'verified',
                'evidence_url' => $decision['evidence_url'] ?? null,
                'evidence_date' => $decision['evidence_date'] ?? null,
                'reviewer' => $decision['reviewer'] ?? null,
            ];
        }

        return [
            'schema_version' => 1,
            'snapshot_id' => $snapshot['snapshot_id'] ?? null,
            'total_legacy_units' => count($rows),
            'verified_mappings' => count($byLegacy),
            'unverified_mappings' => count($rows) - count($byLegacy),
            'mappings' => $rows,
        ];
    }
}
