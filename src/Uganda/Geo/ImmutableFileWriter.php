<?php

declare(strict_types=1);

namespace Uganda\Geo;

use RuntimeException;

/**
 * Build an immutable export artifact in a same-directory temporary file, then
 * publish it without overwriting any existing name, including a symlink.
 *
 * fopen('x') alone is insufficient to reject dangling symlinks under PHP's
 * filesystem wrapper. A hard-link publication is atomic and refuses an
 * occupied destination even when that entry is a dangling symlink.
 */
final class ImmutableFileWriter
{
    public static function write(string $path, string $contents): void
    {
        if (is_link($path) || file_exists($path)) {
            throw new RuntimeException('Cannot create new export file: ' . $path);
        }

        $directory = realpath(dirname($path));
        if ($directory === false || !is_dir($directory)) {
            throw new RuntimeException('Export destination parent does not exist: ' . $path);
        }
        $temporary = @tempnam($directory, '.uganda-geo-stage-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot allocate immutable export staging file: ' . $path);
        }

        try {
            // tempnam may fall back to the system temporary directory when
            // the requested directory is unwritable. Never allow a cross-
            // filesystem hard-link attempt or accidental outside staging.
            if (dirname($temporary) !== $directory) {
                throw new RuntimeException('Export staging file was created outside the target directory');
            }

            $handle = @fopen($temporary, 'wb');
            if ($handle === false) {
                throw new RuntimeException('Cannot write immutable export staging file');
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
            } finally {
                if (!fclose($handle)) {
                    throw new RuntimeException('Could not close completed export file: ' . $path);
                }
            }

            // link() refuses an occupied destination without ever following
            // its symlink target. The final path appears only after a fully
            // written, closed file exists on the same filesystem.
            if (!@link($temporary, $path)) {
                throw new RuntimeException('Cannot publish new export file without replacing an existing entry: ' . $path);
            }
        } finally {
            @unlink($temporary);
        }
    }
}
