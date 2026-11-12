<?php

declare(strict_types=1);

use Uganda\Lookup\UnicodeNameNormalizer;
use Uganda\Uganda;
use Uganda\Village;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$scenario = $argv[1] ?? '';
$iterations = $argv[2] ?? '';
$allowed = [
    'unicode_exact_global', 'unicode_exact_scoped',
    'unicode_prefix_global', 'unicode_prefix_scoped',
    'fuzzy_global', 'fuzzy_scoped', 'fuzzy_unicode_global', 'fuzzy_unicode_scoped',
];
if ($argc !== 3 || !in_array($scenario, $allowed, true)
    || !ctype_digit($iterations) || (int) $iterations < 5 || (int) $iterations > 100) {
    fwrite(STDERR, "Usage: php unicode-search.php SCENARIO ITERATIONS(5..100)\n");
    exit(2);
}
UnicodeNameNormalizer::requireIntl();

$start = hrtime(true);
$geo = new Uganda();
$constructedMs = (hrtime(true) - $start) / 1000000;

$sample = null;
foreach ($geo->iterate('village') as $unit) {
    if ($unit instanceof Village) {
        $sample = $unit;
        break;
    }
}
if (!$sample instanceof Village) {
    throw new RuntimeException('No village in the bundled geography source');
}
$normalized = UnicodeNameNormalizer::normalize($sample->name());
$clusters = UnicodeNameNormalizer::graphemes($normalized);
$prefix = implode('', array_slice($clusters, 0, min(3, count($clusters))));
if ($prefix === '') {
    throw new RuntimeException('Cannot benchmark empty normalized village names');
}
$parentId = $sample->parishId();
$typo = $normalized . 'a';
$operation = match ($scenario) {
    'unicode_exact_global' => static fn (): array => $geo->searchByUnicodeName('village', $normalized),
    'unicode_exact_scoped' => static fn (): array => $geo->searchByUnicodeName('village', $normalized, $parentId),
    'unicode_prefix_global' => static fn (): array => $geo->searchByUnicodePrefix('village', $prefix, null, 10),
    'unicode_prefix_scoped' => static fn (): array => $geo->searchByUnicodePrefix('village', $prefix, $parentId, 10),
    'fuzzy_global' => static fn (): array => $geo->searchFuzzy('village', $typo, null, 10, 2),
    'fuzzy_scoped' => static fn (): array => $geo->searchFuzzy('village', $typo, $parentId, 10, 2),
};

$firstStart = hrtime(true);
$initial = $operation();
$firstMs = (hrtime(true) - $firstStart) / 1000000;
$expected = json_encode(array_map(
    static fn (object $unit): array => [$unit::class, $unit->id()],
    $initial
), JSON_THROW_ON_ERROR);
if ($initial === [] && str_contains($scenario, 'fuzzy')) {
    throw new RuntimeException('Fuzzy workload did not match its source village');
}

$samples = [];
for ($i = 0, $count = (int) $iterations; $i < $count; $i++) {
    $tick = hrtime(true);
    $actual = $operation();
    $samples[] = (hrtime(true) - $tick) / 1000000;
    $serialized = json_encode(array_map(
        static fn (object $unit): array => [$unit::class, $unit->id()],
        $actual
    ), JSON_THROW_ON_ERROR);
    if ($serialized !== $expected) {
        throw new RuntimeException('Unicode search results changed during warm queries');
    }
}

sort($samples, SORT_NUMERIC);
$percentile = static fn (float $p): float => round($samples[max(0, (int) ceil(count($samples) * $p) - 1)], 5);
echo json_encode([
    'scenario' => $scenario,
    'iterations' => (int) $iterations,
    'php' => PHP_VERSION,
    'icu' => defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : 'unknown',
    'constructor_ms' => round($constructedMs, 3),
    'first_query_ms' => round($firstMs, 3),
    'warm_p50_ms' => $percentile(0.5),
    'warm_p95_ms' => $percentile(0.95),
    'warm_p99_ms' => $percentile(0.99),
    'warm_mean_ms' => round(array_sum($samples) / count($samples), 5),
    'first_result_count' => count($initial),
    'fingerprint' => hash('sha256', $expected),
    'memory_peak_reserved_bytes' => memory_get_peak_usage(true),
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
