<?php

declare(strict_types=1);

use Uganda\Import\LegacyJsonImporter;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Import/LegacyJsonImporter.php';

$input = $argv[1] ?? $root . '/src/Uganda/Data';
$output = $argv[2] ?? null;

if ($output === null) {
    fwrite(STDERR, "Usage: php tools/data/import-legacy.php [source-directory] <output-file>\n");
    exit(2);
}

try {
    $snapshot = (new LegacyJsonImporter())->import($input);
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $parent = dirname($output);
    if (!is_dir($parent) || !is_writable($parent) || file_exists($output)) {
        throw new RuntimeException('Output directory must be writable and output must not exist');
    }
    $temp = tempnam($parent, '.geo-');
    if ($temp === false) {
        throw new RuntimeException('Unable to allocate temporary output');
    }
    try {
        if (file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !rename($temp, $output)) {
            throw new RuntimeException('Unable to publish complete snapshot atomically');
        }
    } finally {
        if (file_exists($temp)) {
            unlink($temp);
        }
    }
    fwrite(STDOUT, 'Exported ' . count($snapshot['units']) . " administrative records\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
