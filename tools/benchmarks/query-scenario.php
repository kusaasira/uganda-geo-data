<?php

declare(strict_types=1);

// Identical workload against independent PHP source checkouts, with no
// Composer dependencies or performance assumptions about either revision.
$scenarios = [
    'district_id', 'village_id', 'village_name', 'district_children',
    'village_children', 'village_ancestors',
    'all_counties', 'all_subcounties', 'all_parishes', 'all_villages',
    'district_subcounties', 'district_parishes', 'district_villages',
    'county_parishes', 'county_villages', 'subcounty_villages',
];
if ($argc !== 4 || !in_array($argv[2], $scenarios, true)
    || !ctype_digit($argv[3]) || (int) $argv[3] < 100 || (int) $argv[3] > 100000) {
    fwrite(STDERR, "Usage: php query-scenario.php REPOSITORY_DIR SCENARIO ITERATIONS(100..100000)\n");
    exit(2);
}

$repository = realpath($argv[1]);
if ($repository === false || !is_file($repository . '/src/Uganda/Uganda.php')) {
    fwrite(STDERR, "Repository is missing the Uganda Geo source API\n");
    exit(2);
}

spl_autoload_register(static function (string $class) use ($repository): void {
    if (!str_starts_with($class, 'Uganda\\')) {
        return;
    }
    $path = $repository . '/src/Uganda/' . str_replace('\\', '/', substr($class, 7)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$scenario = $argv[2];
$iterations = (int) $argv[3];
$before = hrtime(true);
$geo = new \Uganda\Uganda();
$constructorMs = (hrtime(true) - $before) / 1000000;

$districtId = null;
$village = null;
foreach ($geo->districts() as $district) {
    foreach ($district->counties() as $county) {
        foreach ($county->subCounties() as $subcounty) {
            foreach ($subcounty->parishes() as $parish) {
                foreach ($parish->villages() as $candidate) {
                    $districtId = $district->id();
                    $selectedDistrict = $district;
                    $selectedCounty = $county;
                    $selectedSubcounty = $subcounty;
                    $village = $candidate;
                    break 5;
                }
            }
        }
    }
}
if ($districtId === null || !$village instanceof \Uganda\Village) {
    fwrite(STDERR, "Cannot locate a representative village\n");
    exit(1);
}

$operations = [
    'district_id' => static fn () => $geo->findById('district', $districtId),
    'village_id' => static fn () => $geo->findById('village', $village->id()),
    'village_name' => static fn () => $geo->searchByName('village', $village->name(), $village->parishId()),
    'district_children' => static fn () => $geo->childrenOf('county', $districtId),
    'village_children' => static fn () => $geo->childrenOf('village', $village->parishId()),
    'village_ancestors' => static fn () => $geo->ancestorsOf('village', $village->id()),
    'all_counties' => static fn () => $geo->counties(),
    'all_subcounties' => static fn () => $geo->subCounties(),
    'all_parishes' => static fn () => $geo->parishes(),
    'all_villages' => static fn () => $geo->villages(),
    'district_subcounties' => static fn () => $selectedDistrict->subCounties(),
    'district_parishes' => static fn () => $selectedDistrict->parishes(),
    'district_villages' => static fn () => $selectedDistrict->villages(),
    'county_parishes' => static fn () => $selectedCounty->parishes(),
    'county_villages' => static fn () => $selectedCounty->villages(),
    'subcounty_villages' => static fn () => $selectedSubcounty->villages(),
];

$operation = $operations[$scenario];
$allocatedAtConstruct = memory_get_usage(true);
$startCold = hrtime(true);
$result = $operation();
$firstLookupUs = (hrtime(true) - $startCold) / 1000;
$allocatedAfterCold = memory_get_usage(true);

$encode = static function ($result): string {
    if (is_object($result)) {
        return get_class($result) . ':' . $result->id();
    }
    return implode('|', array_map(static function (object $unit): string {
        return get_class($unit) . ':' . $unit->id();
    }, $result));
};
$fingerprint = hash('sha256', $encode($result));

$startWarm = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $operation();
}
$warmUs = (hrtime(true) - $startWarm) / $iterations / 1000;
if (hash('sha256', $encode($result)) !== $fingerprint) {
    throw new RuntimeException('Lookup results changed between cold and warm executions');
}

echo json_encode([
    'scenario' => $scenario,
    'samples' => $iterations,
    'fingerprint' => $fingerprint,
    'constructor_ms' => round($constructorMs, 3),
    'first_query_us' => round($firstLookupUs, 3),
    'warm_mean_us' => round($warmUs, 4),
    'memory_after_constructor_bytes' => $allocatedAtConstruct,
    'memory_after_first_query_bytes' => $allocatedAfterCold,
    'memory_peak_bytes' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
