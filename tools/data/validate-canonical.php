<?php

declare(strict_types=1);

use Uganda\Data\CanonicalIntegrityValidator;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Data/CanonicalIntegrityValidator.php';
$input = $argv[1] ?? null;
$sourceDirectory = $argv[2] ?? null;
if ($input === null) {
    fwrite(STDERR, "Usage: php tools/data/validate-canonical.php <snapshot.json> [legacy-source-directory]\n");
    exit(2);
}
try {
    $json = file_get_contents($input);
    if ($json === false) {
        throw new RuntimeException('Unable to read snapshot');
    }
    $snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot)) {
        throw new RuntimeException('Snapshot root must be an object');
    }
    $expected = null;
    if ($sourceDirectory !== null) {
        $files = [
            'district' => 'districts.json',
            'county' => 'counties.json',
            'subcounty' => 'sub_counties.json',
            'parish' => 'parishes.json',
            'village' => 'villages.json',
        ];
        $expected = [];
        foreach ($files as $level => $name) {
            $contents = file_get_contents(rtrim($sourceDirectory, '/') . '/' . $name);
            if ($contents === false) {
                throw new RuntimeException("Unable to read original $level file");
            }
            $rows = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows)) {
                throw new RuntimeException("Original $level file must be a JSON array");
            }
            $expected[$level] = count($rows);
        }
    }
    $report = (new CanonicalIntegrityValidator())->validate($snapshot, $expected);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit($report['valid'] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Validation failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
