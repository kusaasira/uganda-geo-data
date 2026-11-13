<?php

declare(strict_types=1);

namespace Uganda\Sync;

use InvalidArgumentException;
use RuntimeException;

final class SnapshotExporter
{
    /**
     * Export without overwriting a destination. The original source data remains immutable.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function export(array $snapshot, string $format, string $destination): array
    {
        $catalog = new SnapshotCatalog();
        $units = $catalog->units($snapshot);
        $snapshotId = $snapshot['snapshot_id'] ?? null;
        if (!is_string($snapshotId)) {
            throw new InvalidArgumentException('Missing validated source snapshot identity');
        }
        if (!in_array($format, ['json', 'ndjson', 'csv'], true)) {
            throw new InvalidArgumentException('Unsupported snapshot export format');
        }
        $handle = @fopen($destination, 'x');
        if ($handle === false) {
            throw new RuntimeException('Export destination already exists or is not writable');
        }
        try {
            if ($format === 'json') {
                $data = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                $this->write($handle, $data);
            } elseif ($format === 'ndjson') {
                foreach ($units as $unit) {
                    $data = json_encode($unit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
                    $this->write($handle, $data);
                }
            } else {
                if (fputcsv($handle, ['snapshot_id', 'id', 'level', 'name', 'parent_id', 'source_id', 'source_record_id', 'lifecycle_status']) === false) {
                    throw new RuntimeException('Cannot write CSV header');
                }
                foreach ($units as $unit) {
                    $fields = [
                        $snapshotId, $unit['id'], $unit['level'], $unit['name'],
                        $unit['parent_id'], $unit['source_id'], $unit['source_record_id'], $unit['lifecycle_status'],
                    ];
                    $safe = array_map($this->csvCell(...), $fields);
                    if (fputcsv($handle, $safe, ',', '"', '') === false) {
                        throw new RuntimeException('Cannot write CSV record');
                    }
                }
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Cannot flush exported data');
            }
        } catch (\Throwable $exception) {
            fclose($handle);
            @unlink($destination);
            throw $exception;
        }
        fclose($handle);
        $digest = hash_file('sha256', $destination);
        if ($digest === false) {
            @unlink($destination);
            throw new RuntimeException('Cannot checksum exported data');
        }
        return [
            'format' => $format,
            'bytes' => filesize($destination),
            'sha256' => $digest,
            'source_manifest' => $catalog->manifest($snapshot),
        ];
    }

    private function csvCell(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        if (preg_match('/^[\\s\\x{FEFF}]*[=+\\-@]/u', $value) === 1) {
            return "'" . $value;
        }
        return $value;
    }

    /** @param resource $handle */
    private function write($handle, string $contents): void
    {
        $length = strlen($contents);
        $position = 0;
        while ($position < $length) {
            $count = fwrite($handle, substr($contents, $position));
            if ($count === false || $count === 0) {
                throw new RuntimeException('Unable to write entire export');
            }
            $position += $count;
        }
    }
}
