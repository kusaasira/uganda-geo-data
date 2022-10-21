<?php

require 'vendor/autoload.php';

use Uganda\Geo;

/**
 * Fetch all Villages in 
 * 1. Mukono district
 * 2. Mukono Municipality
 * 3. Goma Division
 * 4. Nantabulirwa Ward
 */
$geo = new Geo();
$data = $geo
        ->districts('Mukono')
        ->counties('Mukono Municipality')
        ->sub_counties('Goma Division')
        ->parishes('Nantabulirwa Ward')
        ->villages()->all();
echo $data;
