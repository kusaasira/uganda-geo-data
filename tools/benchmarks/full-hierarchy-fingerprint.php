<?php

declare(strict_types=1);

if ($argc !== 2 || !is_dir($argv[1]) || !is_file(rtrim($argv[1], '/') . '/src/Uganda/Uganda.php')) {
    fwrite(STDERR, "Usage: php full-hierarchy-fingerprint.php REPOSITORY_DIR\n");
    exit(2);
}

$repository = realpath($argv[1]);
if ($repository === false) {
    fwrite(STDERR, "Repository directory cannot be resolved\n");
    exit(2);
}

spl_autoload_register(static function (string $class) use ($repository): void {
    if (!str_starts_with($class, 'Uganda\\')) {
        return;
    }
    $file = $repository . '/src/Uganda/' . str_replace('\\', '/', substr($class, 7)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$start = hrtime(true);
$geo = new \Uganda\Uganda();
$levels = [
    'district' => ['method' => 'districts', 'parent' => null],
    'county' => ['method' => 'counties', 'parent' => 'districtId'],
    'subcounty' => ['method' => 'subCounties', 'parent' => 'countyId'],
    'parish' => ['method' => 'parishes', 'parent' => 'subCountyId'],
    'village' => ['method' => 'villages', 'parent' => 'parishId'],
];
$hash = hash_init('sha256');
$counts = [];

foreach ($levels as $level => $config) {
    $method = $config['method'];
    $parentMethod = $config['parent'];
    $records = $geo->$method();
    $counts[$level] = count($records);
    foreach ($records as $record) {
        $parent = $parentMethod === null ? null : $record->$parentMethod();
        $line = json_encode([$level, $record->id(), $record->name(), $parent], JSON_THROW_ON_ERROR) . "\n";
        hash_update($hash, $line);
    }
    unset($records);
}

echo json_encode([
    'sha256' => hash_final($hash),
    'counts' => $counts,
    'elapsed_ms' => round((hrtime(true) - $start) / 1000000, 2),
    'memory_peak_bytes' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
