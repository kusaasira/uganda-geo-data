<?php

declare(strict_types=1);

use Uganda\Data\ExplorerIndexBuilder;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 3 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php tools/data/build-explorer.php canonical.json NEW_OUTPUT_DIRECTORY\n");
    exit(2);
}

$staging = null;
try {
    $output = rtrim($argv[2], '/');
    if ($output === '' || file_exists($output) || is_link($output)) {
        throw new RuntimeException('Explorer output must be a new, nonexisting directory');
    }

    $snapshot = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot)) {
        throw new UnexpectedValueException('Invalid canonical input snapshot');
    }
    $index = (new ExplorerIndexBuilder())->build($snapshot);

    $parent = dirname($output);
    if (!is_dir($parent) && !mkdir($parent, 0755, true)) {
        throw new RuntimeException('Could not create explorer parent directory');
    }

    $staging = $output . '.partial-' . bin2hex(random_bytes(8));
    if (!mkdir($staging, 0755) || !mkdir($staging . '/assets', 0755)) {
        throw new RuntimeException('Could not create staging directory');
    }
    $root = dirname(__DIR__, 2) . '/resources/explorer';
    foreach (['index.html', 'assets/app.js', 'assets/style.css'] as $name) {
        if (!copy($root . '/' . $name, $staging . '/' . $name)) {
            throw new RuntimeException('Unable to copy explorer asset ' . $name);
        }
    }

    if (file_put_contents(
        $staging . '/data.json',
        json_encode($index, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"
    ) === false) {
        throw new RuntimeException('Unable to write explorer data index');
    }

    if (file_exists($output) || is_link($output) || !rename($staging, $output)) {
        throw new RuntimeException('Explorer destination became unavailable');
    }
    $staging = null;
    fwrite(STDOUT, 'Built offline explorer for ' . $index['unit_count'] . " locations\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(2);
} finally {
    if ($staging !== null && is_dir($staging)) {
        foreach (['index.html', 'data.json', 'assets/app.js', 'assets/style.css'] as $name) {
            if (is_file($staging . '/' . $name)) {
                unlink($staging . '/' . $name);
            }
        }
        if (is_dir($staging . '/assets')) {
            rmdir($staging . '/assets');
        }
        rmdir($staging);
    }
}
