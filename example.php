<?php

require 'vendor/autoload.php';

use Uganda\Geo;

$geo = new Geo();
$data = $geo
        ->districts('Mukono')
        ->counties('Mukono Municipality')
        ->sub_counties('Goma Division')
        ->parishes('Nantabulirwa Ward')
        ->villages()->all();
echo $data;
