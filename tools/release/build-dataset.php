<?php

declare(strict_types=1);

use Uganda\Data\CanonicalIntegrityValidator;
use Uganda\Import\LegacyJsonImporter;
use Uganda\Release\ArtifactManifest;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc !== 3 || !is_dir($argv[1]) || file_exists($argv[2]) || is_link($argv[2])
    || !is_dir(dirname($argv[2]))) {
    fwrite(STDERR, "Usage: php tools/release/build-dataset.php SOURCE_DIRECTORY NEW_OUTPUT_DIRECTORY\n");
    exit(2);
}
$source = realpath($argv[1]);
if ($source === false) {
    fwrite(STDERR, "Invalid source directory\n");
    exit(2);
}
$output = $argv[2];
$stage = $output . '.partial-' . bin2hex(random_bytes(6));

function removeDatasetStage(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    $contents = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($contents as $file) {
        $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($directory);
}

try {
    $files = [
        'district' => 'districts.json',
        'county' => 'counties.json',
        'subcounty' => 'sub_counties.json',
        'parish' => 'parishes.json',
        'village' => 'villages.json',
    ];
    $expected = [];
    $sources = [];
    foreach ($files as $level => $name) {
        $path = $source . '/' . $name;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Missing readable input: ' . $name);
        }
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Could not read ' . $name);
        }
        $rows = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($rows) || array_keys($rows) !== array_keys(array_values($rows))
            || $rows === []) {
            throw new RuntimeException('Source is not a nonempty JSON list: ' . $name);
        }
        $expected[$level] = count($rows);
        $sources[$level] = ['path' => $name, 'bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)];
    }
    $snapshot = (new LegacyJsonImporter())->import($source);
    $report = (new CanonicalIntegrityValidator())->validate($snapshot, $expected);
    if (!$report['valid']) {
        throw new RuntimeException('Canonical snapshot failed record-conservation validation: '
            . implode('; ', $report['errors']));
    }
    $report['source_files'] = $sources;
    if (!mkdir($stage, 0700)) {
        throw new RuntimeException('Unable to stage snapshot release');
    }
    $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    foreach (['canonical.json' => $snapshot, 'qa.json' => $report] as $name => $data) {
        $encoded = json_encode($data, $options) . "\n";
        if (file_put_contents($stage . '/' . $name, $encoded, LOCK_EX) !== strlen($encoded)) {
            throw new RuntimeException('Unable to complete staged artifact: ' . $name);
        }
    }
    $manifest = (new ArtifactManifest())->build($stage, ['canonical.json', 'qa.json']);
    $manifestJson = json_encode($manifest, $options) . "\n";
    if (file_put_contents($stage . '/manifest.json', $manifestJson, LOCK_EX) !== strlen($manifestJson)) {
        throw new RuntimeException('Unable to complete checksum manifest');
    }
    (new ArtifactManifest())->verify($stage, $manifest, ['canonical.json', 'qa.json']);
    if (!rename($stage, $output)) {
        throw new RuntimeException('Unable to publish complete staged release');
    }
    fwrite(STDOUT, "Reproducible canonical dataset generated and verified\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    removeDatasetStage($stage);
}
