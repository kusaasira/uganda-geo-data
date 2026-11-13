<?php

declare(strict_types=1);

namespace Uganda\Sync;

use InvalidArgumentException;
use JsonException;
use Uganda\Data\CanonicalIntegrityValidator;
use Uganda\Import\LegacyJsonImporter;

final class SnapshotCatalog
{
    /** @return array<string, mixed> */
    public function bundled(?string $directory = null): array
    {
        return $this->validate((new LegacyJsonImporter())->import($directory ?? dirname(__DIR__) . '/Data'));
    }

    /** @return array<string, mixed> */
    public function fromFile(string $filename): array
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new InvalidArgumentException('Snapshot is missing or unreadable');
        }
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new InvalidArgumentException('Cannot read snapshot');
        }
        try {
            $snapshot = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Snapshot is not valid JSON', 0, $exception);
        }
        if (!is_array($snapshot)) {
            throw new InvalidArgumentException('Snapshot must be a JSON object');
        }
        /** @var array<string, mixed> $snapshot */
        return $this->validate($snapshot);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function validate(array $snapshot): array
    {
        $result = (new CanonicalIntegrityValidator())->validate($snapshot);
        if (!$result['valid']) {
            $errors = $result['errors'];
            if (!is_array($errors)) {
                throw new InvalidArgumentException('Invalid canonical snapshot');
            }
            throw new InvalidArgumentException('Invalid canonical snapshot: ' . implode('; ', array_map('strval', $errors)));
        }
        return $snapshot;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return list<array{id: string, level: string, name: string, parent_id: string|null,
     *     source_id: string, source_record_id: string, lifecycle_status: string}>
     */
    public function units(array $snapshot): array
    {
        $this->validate($snapshot);
        $rows = $snapshot['units'];
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new InvalidArgumentException('Snapshot units must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['id'] ?? null) ||
                !is_string($row['level'] ?? null) || !is_string($row['name'] ?? null) ||
                !is_string($row['source_id'] ?? null) ||
                !is_string($row['source_record_id'] ?? null) ||
                !is_string($row['lifecycle_status'] ?? null) ||
                !array_key_exists('parent_id', $row) ||
                ($row['parent_id'] !== null && !is_string($row['parent_id']))) {
                throw new InvalidArgumentException('Invalid canonical unit shape');
            }
        }
        /** @var list<array{id: string, level: string, name: string, parent_id: string|null,
         *     source_id: string, source_record_id: string, lifecycle_status: string}> $rows
         */
        return $rows;
    }

    /** @param array<string, mixed> $snapshot */
    public function digest(array $snapshot): string
    {
        $this->validate($snapshot);
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    public function manifest(array $snapshot): array
    {
        $this->validate($snapshot);
        $counts = [];
        $units = $this->units($snapshot);
        foreach ($units as $unit) {
            $counts[$unit['level']] = ($counts[$unit['level']] ?? 0) + 1;
        }
        ksort($counts);
        return [
            'schema_version' => 1,
            'snapshot_id' => $snapshot['snapshot_id'],
            'reference_date' => $snapshot['reference_date'] ?? null,
            'source_ids' => $snapshot['source_ids'],
            'sha256' => $this->digest($snapshot),
            'total_units' => count($units),
            'counts_by_level' => $counts,
        ];
    }
}
