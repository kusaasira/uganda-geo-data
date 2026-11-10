<?php

declare(strict_types=1);

use Uganda\Release\ReleaseEvidence;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc < 2 || $argc > 4) {
    fwrite(STDERR, "Usage: php tools/release/qa-evidence.php canonical-qa.json [topology.json] [coverage.json]\n");
    exit(2);
}

try {
    $read = static function (string $path): array {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read evidence: ' . $path);
        }
        $value = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new UnexpectedValueException('Expected a JSON report in ' . $path);
        }
        return $value;
    };
    $report = (new ReleaseEvidence())->aggregate(
        $read($argv[1]),
        isset($argv[2]) ? $read($argv[2]) : null,
        isset($argv[3]) ? $read($argv[3]) : null
    );
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    if ($report['checks']['canonical_integrity'] !== 'passed'
        || $report['checks']['feature_topology'] === 'failed') {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
