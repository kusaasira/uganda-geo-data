<?php

declare(strict_types=1);

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php verify-instance-churn.php REPORT.json\n");
    exit(2);
}

try {
    $data = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "Invalid instance memory JSON\n");
    exit(2);
}
if (!is_array($data) || ($data['scenario'] ?? null) !== 'instance-churn') {
    fwrite(STDERR, "Invalid instance memory report\n");
    exit(2);
}
foreach (['iterations', 'retained_growth_bytes', 'memory_peak_reserved_bytes'] as $field) {
    if (!isset($data[$field]) || !is_int($data[$field]) || $data[$field] < 0) {
        fwrite(STDERR, "Missing valid integer metric $field\n");
        exit(2);
    }
}

if ($data['iterations'] < 2 || $data['retained_growth_bytes'] > 4194304
    || $data['memory_peak_reserved_bytes'] > 134217728) {
    fwrite(STDERR, "Repeated geography instances retained too much memory\n");
    exit(1);
}
echo sprintf(
    "Instance churn: %d engines, %.1f KiB retained, %.1f MiB peak: passed\n",
    $data['iterations'],
    $data['retained_growth_bytes'] / 1024,
    $data['memory_peak_reserved_bytes'] / 1048576
);
