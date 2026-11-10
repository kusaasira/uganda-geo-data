<?php

declare(strict_types=1);

use Uganda\Data\OfficialSourceReview;

$root = dirname(__DIR__, 2);
require_once $root . '/src/Uganda/Data/OfficialSourceReview.php';

try {
    $raw = file_get_contents($argv[1] ?? $root . '/resources/data/official-source-freshness.json');
    if ($raw === false) {
        throw new RuntimeException('Unable to read source freshness manifest');
    }
    $sources = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($sources)) {
        throw new RuntimeException('Source manifest must be an object');
    }
    $report = (new OfficialSourceReview())->review($sources);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
