<?php

declare(strict_types=1);

use Uganda\Release\GeometryArchive;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php tools/release/package-geometry.php EXPORTED_FORMAT_DIRECTORY VERSION NEW_ARCHIVE.zip\n");
    exit(2);
}
try {
    $manifest = (new GeometryArchive())->build($argv[1], $argv[2], $argv[3]);
    echo json_encode([
        'archive' => $argv[3],
        'file_count' => count($manifest['artifacts']),
        'publication_rights' => $manifest['publication_rights'],
        'release_asset_ready' => false,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
