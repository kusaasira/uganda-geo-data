<?php

declare(strict_types=1);

use Uganda\Data\CanonicalIntegrityValidator;
use Uganda\Data\SnapshotReleaseAuditor;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Data/CanonicalIntegrityValidator.php';
require_once $root . '/src/Uganda/Data/CanonicalSnapshotDiffer.php';
require_once $root . '/src/Uganda/Data/SnapshotReleaseAuditor.php';

if (count($argv) !== 3) {
    fwrite(STDERR, "Usage: php tools/data/audit-snapshot-release.php <before.json> <after.json>\n");
    exit(2);
}

try {
    $snapshots = [];
    $hashes = [];
    foreach ([$argv[1], $argv[2]] as $path) {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read canonical snapshot: ' . $path);
        }
        $snapshot = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) {
            throw new RuntimeException('Canonical snapshot must be a JSON object');
        }
        $validation = (new CanonicalIntegrityValidator())->validate($snapshot);
        if (!$validation['valid']) {
            throw new RuntimeException('Invalid canonical snapshot: ' . $path);
        }
        $snapshots[] = $snapshot;
        $hashes[] = hash('sha256', $bytes);
    }
    $report = (new SnapshotReleaseAuditor())->audit($snapshots[0], $snapshots[1]);
    $report['input_sha256'] = ['before' => $hashes[0], 'after' => $hashes[1]];
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Snapshot release audit failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
