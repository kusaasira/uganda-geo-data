<?php

declare(strict_types=1);

use Uganda\Uganda;

require dirname(__DIR__) . '/vendor/autoload.php';

$geo = new Uganda();
$districts = $geo->districts();
$district = reset($districts);
if (!$district instanceof Uganda\District) {
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
$countyCount = count($geo->childrenOf('county', $district->id()));
$example = [
    'district' => ['id' => $district->id(), 'name' => $district->name()],
    'county_count' => $countyCount,
    'matched_by_id' => true,
    'name_search_matches' => count($matches),
];
fwrite(STDOUT, json_encode($example, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
