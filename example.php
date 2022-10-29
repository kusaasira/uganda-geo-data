<?php

use Uganda\Exceptions\DistrictNotFoundException;
use Uganda\Exceptions\ParishNotFoundException;
use Uganda\Uganda;

require 'vendor/autoload.php';

$uganda = new Uganda();

/**
 * Fetch all Villages in Uganada
 */
$villages = $uganda->villages();

foreach ($villages as $village) {
    echo $village->name();
}

/**
 * Fetch all Sub Counties in a District
 */

try {
    $subCountiesInDistrict = $uganda->district('Bukomansimbi')->subCounties();
} catch (DistrictNotFoundException $e) {
    // Calling for a Specific Location can throw an Exception if it doesn't exist
}

/**
 * Getting all the Parish information as an array
 */
try {
    $parishInformation = $uganda->parish('Akwangagwel')->toArray();
} catch (ParishNotFoundException $e) {
    // Calling for a Specific Location can throw an Exception if it doesn't exist
}