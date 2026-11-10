<?php

declare(strict_types=1);

use Uganda\Release\ReleaseNotes;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php tools/release/build-notes.php SOURCE.json RELEASE_TAG OUTPUT.md\n");
    exit(2);
}
try {
    if (file_exists($argv[3]) || is_link($argv[3]) || !is_dir(dirname($argv[3]))) {
        throw new RuntimeException('Release notes must be written to an unused path');
    }
    $source = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($source)) {
        throw new UnexpectedValueException('Release-note manifest must be a JSON object');
    }
    $rendered = (new ReleaseNotes())->render($source, $argv[2]);
    $handle = fopen($argv[3], 'x');
    if ($handle === false) {
        throw new RuntimeException('Cannot create release notes');
    }
    try {
        $length = strlen($rendered);
        $done = 0;
        while ($done < $length) {
            $written = fwrite($handle, substr($rendered, $done));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Incomplete release notes write');
            }
            $done += $written;
        }
    } finally {
        fclose($handle);
    }
    fwrite(STDOUT, "Deterministic release notes generated\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
