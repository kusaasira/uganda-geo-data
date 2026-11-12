<?php

declare(strict_types=1);

namespace Uganda\Geo;

use RuntimeException;
use Throwable;

/**
 * Write an immutable staged export file without following existing symlinks
 * or accepting short writes. The caller publishes its complete stage only
 * after every file and external conversion has been verified.
 */
final class ImmutableFileWriter
{
    public static function write(string $path, string $contents): void
    {
        // 'x' creates a new file only; unlike file_put_contents this cannot
        // truncate an existing target between file_exists() and open().
        $handle = @fopen($path, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Cannot create new export file: ' . $path);
        }

        try {
            $length = strlen($contents);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($handle, substr($contents, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Incomplete export file write: ' . $path);
                }
                $offset += $written;
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Could not flush complete export file: ' . $path);
            }
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);

            throw $exception;
        }

        if (!fclose($handle)) {
            @unlink($path);
            throw new RuntimeException('Could not close completed export file: ' . $path);
        }
    }
}
