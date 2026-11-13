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
        return $this->validate($snapshot);
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    public function validate(array $snapshot): array
    {
        $result = (new CanonicalIntegrityValidator())->validate($snapshot);
        if (!$result['valid']) {
            throw new InvalidArgumentException('Invalid canonical snapshot: ' . implode('; ', $result['errors']));
        }
        return $snapshot;
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
        foreach ($snapshot['units'] as $unit) {
            $counts[$unit['level']] = ($counts[$unit['level']] ?? 0) + 1;
        }
        ksort($counts);
        return [
            'schema_version' => 1,
            'snapshot_id' => $snapshot['snapshot_id'],
            'reference_date' => $snapshot['reference_date'] ?? null,
            'source_ids' => $snapshot['source_ids'],
            'sha256' => $this->digest($snapshot),
            'total_units' => count($snapshot['units']),
            'counts_by_level' => $counts,
        ];
    }
}
