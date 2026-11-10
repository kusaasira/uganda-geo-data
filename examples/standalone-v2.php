<?php

declare(strict_types=1);

use Uganda\Uganda;
use Uganda\District;

require dirname(__DIR__) . '/vendor/autoload.php';

$geo = new Uganda();
$districts = $geo->districts();
$district = reset($districts);
if (!$district instanceof District) {
    throw new RuntimeException('The administrative dataset has no districts');
}
$byId = $geo->findById('district', $district->id());
if ($byId !== $district) {
    throw new RuntimeException('ID identity lookup must return the same district');
}
$matches = $geo->searchByName('district', $district->name());
if (!in_array($district, $matches, true)) {
    throw new RuntimeException('Name search did not preserve the district identity');
}
$counties = $geo->childrenOf('county', $district->id());
if ($geo->findById('district', PHP_INT_MAX) !== null) {
    throw new RuntimeException('Unknown district IDs must return null');
}
if ($counties !== []) {
    $county = reset($counties);
    if (!in_array($county, $geo->searchByName('county', $county->name(), $district->id()), true)) {
        throw new RuntimeException('Parent-scoped name lookup failed');
    }
}
$countyCount = count($counties);
$example = [
    'district' => ['id' => $district->id(), 'name' => $district->name()],
    'county_count' => $countyCount,
    'matched_by_id' => true,
    'name_search_matches' => count($matches),
];
fwrite(STDOUT, json_encode($example, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
