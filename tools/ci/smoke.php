<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Uganda\Uganda;

$uganda = new Uganda();
$districts = $uganda->districts();

if ($districts === []) {
    throw new RuntimeException('The bundled administrative dataset is empty');
}

$district = reset($districts);
if ($district === false || $uganda->findById('district', $district->id()) !== $district) {
    throw new RuntimeException('District lookup and identity are inconsistent');
}

$counties = $district->counties();
if ($counties === []) {
    throw new RuntimeException('The example district has no counties');
}

$county = reset($counties);
if ($county === false || $uganda->findById('county', $county->id()) !== $county) {
    throw new RuntimeException('County lookup and identity are inconsistent');
}

fwrite(STDOUT, "Standalone Composer autoload and administrative identity smoke passed\n");
