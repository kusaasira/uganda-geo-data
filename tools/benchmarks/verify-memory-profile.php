<?php

declare(strict_types=1);

// Byte-accurate emalloc measurements are distinct from allocator reservation.
// The PHP allocator can retain free pages even when live object usage drops.
if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php verify-memory-profile.php PROFILE.json\n");
    exit(2);
}

try {
    $result = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Memory profile is not valid JSON\n");
    exit(2);
}

$fields = [
    'memory_active_after_constructor_bytes', 'memory_active_after_warmup_bytes',
    'memory_active_after_first_pass_bytes', 'memory_active_after_second_pass_bytes',
    'memory_peak_active_bytes', 'memory_peak_reserved_bytes',
    'retained_growth_second_pass_bytes', 'records_visited_per_pass',
];

if (!is_array($result) || !is_string($result['scenario'] ?? null)) {
    fwrite(STDERR, "Memory profile is not a JSON object with a scenario\n");
    exit(2);
}
foreach ($fields as $field) {
    if (!isset($result[$field]) || !is_int($result[$field]) || $result[$field] < 0) {
        fwrite(STDERR, "Invalid integer memory metric: $field\n");
        exit(2);
    }
}

$peakBudget = 134217728; // 128 MiB, below ordinary PHP 256M defaults
$retainedBudget = 8388608; // no more than 8 MiB of new live allocations
if ($result['memory_peak_reserved_bytes'] > $peakBudget) {
    fwrite(STDERR, "Memory peak exceeds 128 MiB budget\n");
    exit(1);
}
if ($result['retained_growth_second_pass_bytes'] > $retainedBudget) {
    fwrite(STDERR, "Second-pass memory retention exceeds 8 MiB budget\n");
    exit(1);
}
if ($result['records_visited_per_pass'] < 1) {
    fwrite(STDERR, "Memory scenario did not exercise a geographic record\n");
    exit(1);
}

echo sprintf(
    "%s: %.1f MiB reserved peak; %.1f KiB second-pass retained growth; budgets passed\n",
    $result['scenario'],
    $result['memory_peak_reserved_bytes'] / 1048576,
    $result['retained_growth_second_pass_bytes'] / 1024
);
