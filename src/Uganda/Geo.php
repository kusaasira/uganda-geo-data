<?php

namespace Uganda;

use Uganda\Util\Helpers;
use Uganda\Util\District;
use Uganda\Util\County;
use Uganda\Util\SubCounty;
use Uganda\Util\Parish;
use Uganda\Util\Village;

class Geo {
    use Helpers, District, County, SubCounty, Parish, Village;

    public function all() {
        $properties = get_object_vars($this);
        header("Content-Type:application/json");

        if (array_key_exists("_error", $properties)) {
            $error['errors'] = $properties['_error'];
            return json_encode($error);
        }
        return json_encode(end($properties));
    }
}
