<?php

namespace Uganda;

use Uganda\Util\Helpers;

class Uganda
{
    use Helpers, District, County, SubCounty, Parish;

    public function all()
    {
        $properties = get_object_vars($this);
        header("Content-Type:application/json");

        if (array_key_exists("_error", $properties)) {
            $error['errors'] = $properties['_error'];
            return json_encode($error);
        }
        return json_encode(end($properties));
    }
}
