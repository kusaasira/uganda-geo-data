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
    if (file_put_contents($argv[2], $json) === false) {
        throw new RuntimeException('Could not write release manifest');
    }
    fwrite(STDOUT, "Release checksum manifest generated\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
