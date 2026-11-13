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
                    $name = $unit['name'];
                    if (preg_match('/^\s*[=+\-@]/u', $name)) {
                        $name = "'" . $name;
                    }
                    if (fputcsv($handle, [
                        (string) $snapshot['snapshot_id'], $unit['id'], $unit['level'], $name,
                        $unit['parent_id'], $unit['source_id'], $unit['source_record_id'], $unit['lifecycle_status'],
                    ]) === false) {
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
