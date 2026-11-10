<?php

declare(strict_types=1);

use Uganda\Release\ReleaseReadiness;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc < 2 || $argc > 3 || (isset($argv[2]) && $argv[2] !== '--enforce')) {
    fwrite(STDERR, "Usage: php tools/release/assess-readiness.php EVIDENCE.json [--enforce]\n");
    exit(2);
}
try {
    $raw = file_get_contents($argv[1]);
    if (!is_string($raw)) {
        throw new RuntimeException('Unable to read readiness evidence');
    }
    $attestation = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($attestation)) {
        throw new UnexpectedValueException('Expected JSON readiness evidence');
    }
    $head = [];
    $status = 0;
    exec('git rev-parse HEAD 2>&1', $head, $status);
    if ($status !== 0 || !isset($head[0])) {
        throw new RuntimeException('Unable to resolve the exact reviewed Git commit');
    }
    $report = (new ReleaseReadiness())->assess($attestation, trim($head[0]));
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
    if (isset($argv[2]) && $argv[2] === '--enforce' && $report['status'] === 'blocked') {
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
