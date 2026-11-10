<?php

declare(strict_types=1);

use Uganda\Data\CanonicalSnapshotDiffer;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Data/CanonicalSnapshotDiffer.php';
if (!isset($argv[1], $argv[2])) {
    fwrite(STDERR, "Usage: php tools/data/diff-snapshots.php <before.json> <after.json>\n");
    exit(2);
}
try {
    $snapshots = [];
    foreach ([$argv[1], $argv[2]] as $path) {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Unable to read snapshot: $path");
        }
        $snapshot = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) {
            throw new RuntimeException("Invalid snapshot document: $path");
        }
        $snapshots[] = $snapshot;
    }
    $result = (new CanonicalSnapshotDiffer())->compare($snapshots[0], $snapshots[1]);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
