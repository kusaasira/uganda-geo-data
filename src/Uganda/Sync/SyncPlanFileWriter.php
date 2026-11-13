<?php

declare(strict_types=1);

namespace Uganda\Sync;

use RuntimeException;
use Throwable;

/** Creates sensitive reviewed plans without exposing local record IDs to other OS users. */
final class SyncPlanFileWriter
{
    /** @param array<string, mixed> $plan */
    public function writeNew(array $plan, string $filename): void
    {
        $content = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $originalMask = umask(0077);
        try {
            $handle = @fopen($filename, 'x');
        } finally {
            umask($originalMask);
        }
        if ($handle === false) {
            throw new RuntimeException('Plan file already exists or is not writable');
        }
        try {
            if (!@chmod($filename, 0600)) {
                throw new RuntimeException('Cannot restrict reviewed plan file permissions');
            }
            $position = 0;
            $length = strlen($content);
            while ($position < $length) {
                $written = fwrite($handle, substr($content, $position));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Cannot write complete synchronization plan');
                }
                $position += $written;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Cannot flush complete synchronization plan');
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($filename);
            throw $exception;
        }
        if (!fclose($handle)) {
            @unlink($filename);
            throw new RuntimeException('Cannot close synchronization plan output');
        }
    }
}
