<?php

declare(strict_types=1);

use Uganda\Release\ArtifactManifest;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php tools/release/verify-manifest.php RELEASE_ROOT manifest.json required-file [required-file ...]\n");
    exit(2);
}

try {
    $manifest = json_decode((string) file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new UnexpectedValueException('Invalid release manifest');
    }
    (new ArtifactManifest())->verify($argv[1], $manifest, array_slice($argv, 3));
    fwrite(STDOUT, "All recorded artifact checksums verified\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
