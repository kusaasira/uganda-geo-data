<?php

declare(strict_types=1);

use Uganda\Data\ExplorerIndexBuilder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 3 || !is_file($argv[1]) || file_exists($argv[2])) {
    fwrite(STDERR, "Usage: php tools/data/build-explorer.php canonical.json NEW_OUTPUT_DIRECTORY\n");
    exit(2);
}

try {
    $snapshot = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot)) {
        throw new UnexpectedValueException('Invalid canonical input snapshot');
    }
    $index = (new ExplorerIndexBuilder())->build($snapshot);
    if (!mkdir($argv[2], 0755, true) || !mkdir($argv[2] . '/assets', 0755)) {
        throw new RuntimeException('Could not create explorer output directories');
    }
    $root = dirname(__DIR__, 2) . '/resources/explorer';
    foreach (['index.html', 'assets/app.js', 'assets/style.css'] as $name) {
        if (!copy($root . '/' . $name, $argv[2] . '/' . $name)) {
            throw new RuntimeException('Unable to copy explorer asset ' . $name);
        }
    }
    if (file_put_contents($argv[2] . '/data.json', json_encode($index, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n") === false) {
        throw new RuntimeException('Unable to write public explorer index');
    }
    fwrite(STDOUT, 'Built offline explorer for ' . $index['unit_count'] . " locations\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
}
