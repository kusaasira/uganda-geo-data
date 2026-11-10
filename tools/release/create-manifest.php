<?php

declare(strict_types=1);

use Uganda\Release\ArtifactManifest;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php tools/release/create-manifest.php RELEASE_ROOT manifest.json relative-file [relative-file ...]\n");
    exit(2);
}

try {
    $manifest = (new ArtifactManifest())->build($argv[1], array_slice($argv, 3));
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    // Use an exclusive create to prevent overwriting a published manifest or
    // following an existing output symlink.
    $handle = fopen($argv[2], 'x');
    if ($handle === false) {
        throw new RuntimeException('Manifest destination already exists or is not writable');
    }
    try {
        if (fwrite($handle, $json) !== strlen($json)) {
            throw new RuntimeException('Could not write complete release manifest');
        }
    } finally {
        fclose($handle);
    }
    fwrite(STDOUT, "Release checksum manifest generated\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
